<?php

namespace JMS\RstBundle\Transformer;
use Symfony\Component\CssSelector\CssSelectorConverter;

/**
 * Transforms the default sphinx output into output compatible with Twitter Bootstrap2.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class TwitterBootstrap2Transformer implements TransformerInterface
{
    private $cssSelector;
    
    public function __construct()
    {
        $this->cssSelector = new CssSelectorConverter();
    }

    public function transform(\DOMDocument $doc, \DOMXPath $xpath, $rootDir)
    {
        $this->cleanUpUnusedAttributes($doc, $xpath);
        $this->rewriteConfigurationBlocks($doc, $xpath);

        $this->rewriteAdmonitions($doc, $xpath, 'note', null, 'icon-pencil');
        $this->rewriteAdmonitions($doc, $xpath, 'tip', 'alert-info', 'icon-eye-open');
        $this->rewriteAdmonitions($doc, $xpath, 'warning', 'alert-error', 'icon-warning-sign');

        $this->rewriteVersion($doc, $xpath, 'versionadded', 'label-success');
    }

    private function rewriteVersion(\DOMDocument $doc, \DOMXPath $xpath, $versionClass, $labelClass = null)
    {
        foreach ($xpath->query($this->cssSelector->toXPath('p.'.$versionClass)) as $pElem) {
            $label = $xpath->query($this->cssSelector->toXPath('span.versionmodified'), $pElem)->item(0);
            $label->setAttribute('class', 'label'.($labelClass ? ' '.$labelClass : ''));
            $text = substr($label->nodeValue, 0, -2);
            $label->nodeValue = $text;

            $pElem->insertBefore($doc->createTextNode(' '), $label->nextSibling);
        }
    }

    private function rewriteAdmonitions(\DOMDocument $doc, \DOMXPath $xpath, $admonitionClass, $alertClass = null, $iconClass = null)
    {
        foreach ($xpath->query($this->cssSelector->toXPath('div.admonition.'.$admonitionClass)) as $divElem) {
            $divElem->setAttribute('class', 'admonition alert'.($alertClass ? ' '.$alertClass : ''));

            $noteElem = $xpath->query($this->cssSelector->toXPath('p.first'), $divElem)->item(0);
            $noteContentElem = $xpath->query($this->cssSelector->toXPath('p.last'), $divElem)->item(0);

            if (null !== $iconClass) {
                $divElem->appendChild($iconElem = new \DOMElement('i'));
                $iconElem->setAttribute('class', $iconClass);
                $iconElem->appendChild($doc->createTextNode(''));
                $divElem->appendChild($doc->createTextNode(' '));
            }

            $divElem->appendChild($newNoteElem = new \DOMElement('strong'));
            foreach ($noteElem->childNodes as $childNode) {
                $newNoteElem->appendChild($childNode);
            }
            $newNoteElem->appendChild($doc->createTextNode(': '));

            while (null !== $firstChild = $noteContentElem->firstChild) {
                $divElem->appendChild($firstChild);

                try {
                    $noteContentElem->removeChild($firstChild);
                } catch (\DOMException $e) {
                    // Element was not found because it was removed automatically
                }
            }

            $divElem->removeChild($noteElem);
            $divElem->removeChild($noteContentElem);
        }
    }

    private function rewriteConfigurationBlocks(\DOMDocument $doc, \DOMXPath $xpath)
    {
        $i = 0;
        foreach ($xpath->query($this->cssSelector->toXPath('div.configuration-block')) as $divElem) {
            $divElem->setAttribute('class', 'configuration-block tabbable');

            foreach ($xpath->query('./ul', $divElem) as $ulElem) {
                $ulElem->setAttribute('class', 'nav nav-tabs');
            }

            $xpath->query($this->cssSelector->toXPath('ul > li:first-child'), $divElem)->item(0)->setAttribute('class', 'active');
            $divElem->appendChild($contentElem = new \DOMElement('div'));
            $contentElem->setAttribute('class', 'tab-content');

            $j = 0;
            foreach ($xpath->query($this->cssSelector->toXPath('ul > li'), $divElem) as $liElem) {
                $titleElem = $xpath->query('./em', $liElem)->item(0);

                $tabElem = $xpath->query('./div', $liElem)->item(0);
                $tabElem->setAttribute('class', 'tab-pane'.($j == 0 ? ' active' : ''));
                $tabElem->setAttribute('id', 'configuration-block-'.$i.'-'.$j);
                $contentElem->appendChild($tabElem);

                foreach ($liElem->childNodes as $node) {
                    $liElem->removeChild($node);
                }

                $liElem->appendChild($linkElem = new \DOMElement('a', $titleElem->nodeValue));
                $linkElem->setAttribute('href', '#configuration-block-'.$i.'-'.$j);
                $linkElem->setAttribute('data-toggle', 'tab');
                $j += 1;
            }

            $i += 1;
        }
    }

    private function cleanUpUnusedAttributes(\DOMDocument $doc, \DOMXpath $xpath)
    {
        foreach ($xpath->query('//table') as $tableElem) {
            $tableElem->setAttribute('class', 'table table-bordered table-striped');
            $tableElem->removeAttribute('border');
        }

        foreach ($xpath->query('//thead') as $theadElem) {
            $theadElem->removeAttribute('valign');
        }

        foreach ($xpath->query('//tbody') as $tbodyElem) {
            $tbodyElem->removeAttribute('valign');
        }

        foreach ($xpath->query('//th') as $thElem) {
            $thElem->removeAttribute('class');
        }

        foreach ($xpath->query('//tr') as $trElem) {
            $trElem->removeAttribute('class');
        }
    }
}