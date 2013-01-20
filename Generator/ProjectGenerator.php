<?php

namespace JMS\RstBundle\Generator;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use JMS\RstBundle\Model\File;
use JMS\RstBundle\LinkRewriter\LinkRewriterInterface;
use Symfony\Component\CssSelector\CssSelector;
use JMS\RstBundle\Transformer\TransformerInterface;
use JMS\RstBundle\Model\Project;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ProjectGenerator
{
    private $sphinxPath;
    private $configPath;
    private $transformers = array();
    private $linkRewriter;

    public function __construct($sphinxPath, $configPath)
    {
        if (!is_dir($configPath)) {
            throw new \InvalidArgumentException(sprintf('The config path "%s" does not exist.', $configPath));
        }

        $this->sphinxPath = $sphinxPath;
        $this->configPath = $configPath;
    }

    public function setLinkRewriter(LinkRewriterInterface $linkRewriter)
    {
        $this->linkRewriter = $linkRewriter;
    }

    public function setTransformers(array $transformers)
    {
        $this->transformers = $transformers;
    }

    public function addTransformer(TransformerInterface $transformer)
    {
        $this->transformers[] = $transformer;
    }

    public function generate($docPath)
    {
        $outputDir = tempnam(sys_get_temp_dir(), uniqid());
        $fs = new Filesystem();
        $fs->remove($outputDir);
        $fs->mkdir($outputDir, 0777);

        $cmd = escapeshellarg($this->sphinxPath).' -c '.escapeshellarg($this->configPath).' -b json '.escapeshellarg($docPath).' '.escapeshellarg($outputDir);

        // make paths relative to cygwin on Windows as sphinx-build only runs with it
        // TODO: This makes a few assumptions about the set-up which should probably be configurable
        if (0 === strpos(PHP_OS, 'WIN')) {
            $cmd = str_replace('C:\\', '/cygdrive/c/', $cmd);
            $cmd = str_replace('\\', '/', $cmd);
            $cmd = 'C:\cygwin\bin\bash -c "'.$cmd.'"';
        }

        $proc = new Process($cmd);
        if (0 !== $proc->run()) {
            throw new ProcessFailedException($proc);
        }

        $project = new Project();
        foreach (Finder::create()->files()->in($docPath)->name('*.rst') as $file) {
            $basename = str_replace('\\', '/', substr(realpath($file), strlen(realpath($docPath))+1, -4));
            $data = json_decode(file_get_contents($outputDir.'/'.$basename.'.fjson'), true);

            if (null !== $this->linkRewriter) {
                $this->linkRewriter->setCurrentFile($basename);
            }

            $data['body'] = $this->postProcessBody($data['body'], $outputDir);
            $data['toc'] = $this->postProcessTableOfContents($data['toc']);

            if (null !== $this->linkRewriter) {
                if ($data['prev'] !== null) {
                    $data['prev']['link'] = $this->linkRewriter->rewriteHref($data['prev']['link']);
                }
                if ($data['next'] !== null) {
                    $data['next']['link'] = $this->linkRewriter->rewriteHref($data['next']['link']);
                }
                if (null !== $data['parents']) {
                    foreach ($data['parents'] as $i => $parent) {
                        $data['parents'][$i]['link'] = $this->linkRewriter->rewriteHref($data['parents'][$i]['link']);
                    }
                }
            }

            $project->addFile(new File(
                $basename,
                $data['title'],
                $data['body'],
                $data['toc'],
                $data['display_toc'],
                $data['parents'],
                $data['prev'],
                $data['next']
            ));
        }

        return $project;
    }

    private function postProcessTableOfContents($toc)
    {
        $xml = simplexml_load_string($toc);
        $uls = $xml->xpath(CssSelector::toXPath('ul'));

        if (!isset($uls[1])) {
            return $toc;
        }

        return $uls[1]->saveXml();
    }

    private function postProcessBody($body, $rootDir)
    {
        $doc = new \DOMDocument('1.0', 'utf-8');
        $doc->loadHTML(utf8_decode($body));
        $xpath = new \DOMXPath($doc);

        foreach ($this->transformers as $transformer) {
            $transformer->transform($doc, $xpath, $rootDir);
        }

        if (null !== $this->linkRewriter) {
            // rewrite links
            foreach ($xpath->query('//a') as $aElem) {
                $aElem->setAttribute('href', $this->linkRewriter->rewriteHref($aElem->getAttribute('href')));
            }
        }

        $html = $doc->saveHTML();

        return preg_replace('#^.*?<body>(.*)</body>.*?$#s', '\\1', $html);
    }
}