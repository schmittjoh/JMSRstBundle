<?php

namespace JMS\RstBundle\Generator;

use JMS\RstBundle\PreProcessor\PreProcessorInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use JMS\RstBundle\Model\File;
use JMS\RstBundle\LinkRewriter\LinkRewriterInterface;
use JMS\RstBundle\Transformer\TransformerInterface;
use JMS\RstBundle\Model\Project;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ProjectGenerator implements LoggerAwareInterface
{
    private $sphinxPath;
    private $configPath;
    private $fs;

    /** @var ProjectBuilderInterface[] */
    private $builders = array();

    /** @var PreProcessorInterface[] */
    private $preProcessors = array();

    /** @var TransformerInterface[] */
    private $transformers = array();

    /** @var LinkRewriterInterface */
    private $linkRewriter;

    public function __construct($sphinxPath, $configPath, Filesystem $fs = null, LoggerInterface $logger = null)
    {
        if (!is_dir($configPath)) {
            throw new \InvalidArgumentException(sprintf('The config path "%s" does not exist.', $configPath));
        }

        $this->sphinxPath = $sphinxPath;
        $this->configPath = $configPath;
        $this->fs = $fs ?: new Filesystem();
        $this->setLogger($logger ?: new NullLogger());
    }

    public function setLogger(LoggerInterface $logger)
    {
        foreach ($this->builders as $builder) {
            if ($builder instanceof LoggerAwareInterface) {
                $builder->setLogger($logger);
            }
        }

        foreach ($this->preProcessors as $processor) {
            if ($processor instanceof LoggerAwareInterface) {
                $processor->setLogger($processor);
            }
        }

        foreach ($this->transformers as $transformer) {
            if ($transformer instanceof LoggerAwareInterface) {
                $transformer->setLogger($logger);
            }
        }

        if ($this->linkRewriter instanceof LoggerAwareInterface) {
            $this->linkRewriter->setLogger($logger);
        }
    }

    public function addPreProcessor(PreProcessorInterface $preProcessor)
    {
        $this->preProcessors[] = $preProcessor;
    }

    public function addProjectBuilder(ProjectBuilderInterface $builder)
    {
        $this->builders[] = $builder;
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
        $tmpFolder = $this->prepare($docPath);

        $outputDir = tempnam(sys_get_temp_dir(), uniqid());
        $fs = new Filesystem();
        $fs->remove($outputDir);
        $fs->mkdir($outputDir, 0777);

        $cmd = escapeshellarg($this->sphinxPath).' -c '.escapeshellarg($this->configPath).' -b json '.escapeshellarg($tmpFolder).' '.escapeshellarg($outputDir);

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

        if (null !== $this->linkRewriter) {
            $paths = array();
            foreach (Finder::create()->files()->in($tmpFolder)->name('*.rst') as $file) {
                /** @var $file SplFileInfo */

                $paths[] = substr($file->getRelativePathname(), 0, -4);
            }
            $this->linkRewriter->setPaths($paths);
        }

        foreach (Finder::create()->files()->in($tmpFolder)->name('*.rst') as $file) {
            $basename = substr($file->getRelativePathname(), 0, -4);
            $data = json_decode(file_get_contents($outputDir.'/'.$basename.'.fjson'), true);

            if (null !== $this->linkRewriter) {
                $this->linkRewriter->setCurrentFile($basename);
            }

            // Workaround for problems caused in some of the transformers.
            $data['body'] = strtr($data['body'], array("\u{2018}" => "'", "\u{2019}" => "'"));

            $data['body'] = $this->postProcessBody($data['body'], $outputDir, $basename);
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

        $this->fs->remove($tmpFolder);

        return $project;
    }

    private function prepare($docPath)
    {
        $tmpFolder = tempnam(sys_get_temp_dir(), 'rst-project');
        $this->fs->remove($tmpFolder);

        $proc = new Process('cp -R '.escapeshellarg($docPath).' '.$tmpFolder);
        if (0 !== $proc->run()) {
            throw new ProcessFailedException($proc);
        }

        foreach ($this->builders as $builder) {
            $builder->build($tmpFolder);
        }

        if ( ! empty($this->preProcessors)) {
            foreach (Finder::create()->files()->in($tmpFolder)->name('*.rst') as $file) {
                /** @var $file SplFileInfo */

                $oldContent = $content = $file->getContents();
                foreach ($this->preProcessors as $processor) {
                    $content = $processor->prepare($content);
                }

                if ($oldContent !== $content) {
                    file_put_contents($file->getRealPath(), $content);
                }
            }
        }

        return $tmpFolder;
    }

    private function postProcessTableOfContents($toc)
    {
        $xml = simplexml_load_string($toc);
        $uls = $xml->xpath((new CssSelectorConverter())->toXPath('ul'));

        if (!isset($uls[1])) {
            return $toc;
        }

        return $uls[1]->saveXml();
    }

    private function postProcessBody($body, $rootDir, $pathname)
    {
        $doc = new \DOMDocument('1.0', 'utf-8');
        $doc->loadHTML(utf8_decode($body));
        $xpath = new \DOMXPath($doc);

        foreach ($this->transformers as $transformer) {
            if ($transformer instanceof PathAware) {
                $transformer->setCurrentPathname($pathname);
            }

            $transformer->transform($doc, $xpath, $rootDir);
        }

        if (null !== $this->linkRewriter) {
            // rewrite links
            foreach ($xpath->query('//a') as $aElem) {
                /** @var \DOMElement $aElem */

                if ( ! $aElem->hasAttribute('href')) {
                    continue;
                }

                $aElem->setAttribute('href', $this->linkRewriter->rewriteHref($aElem->getAttribute('href')));
            }
        }

        $html = $doc->saveHTML();

        return preg_replace('#^.*?<body>(.*)</body>.*?$#s', '\\1', $html);
    }
}
