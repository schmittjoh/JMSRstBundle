<?php

namespace JMS\RstBundle\Transformer;

use Symfony\Component\CssSelector\CssSelector;

/**
 * Transforms the default sphinx output into output compatible with Twitter Bootstrap2.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class TwitterBootstrap2Transformer implements TransformerInterface
{
    public function transform(\DOMDocument $doc, \DOMXPath $xpath)
    {
        $this->cleanUpUnusedAttributes($doc, $xpath);
        $this->rewriteConfigurationBlocks($doc, $xpath);

        $this->rewriteAdmonitions($doc, $xpath, 'note', null, 'icon-pencil');
        $this->rewriteAdmonitions($doc, $xpath, 'tip', 'alert-info', 'icon-eye-open');
    }

    private function rewriteAdmonitions(\DOMDocument $doc, \DOMXPath $xpath, $admonitionClass, $alertClass = null, $iconClass = null)
    {
        foreach ($xpath->query(CssSelector::toXPath('div.admonition.'.$admonitionClass)) as $divElem) {
            $divElem->setAttribute('class', 'admonition alert'.($alertClass ? ' '.$alertClass : ''));

            $noteElem = $xpath->query(CssSelector::toXPath('p.first'), $divElem)->item(0);
            $noteContentElem = $xpath->query(CssSelector::toXPath('p.last'), $divElem)->item(0);

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
        foreach ($xpath->query(CssSelector::toXPath('div.configuration-block')) as $divElem) {
            $divElem->setAttribute('class', 'configuration-block tabbable');

            foreach ($xpath->query('./ul', $divElem) as $ulElem) {
                $ulElem->setAttribute('class', 'nav nav-tabs');
            }

            $xpath->query(CssSelector::toXPath('ul > li:first-child'), $divElem)->item(0)->setAttribute('class', 'active');
            $divElem->appendChild($contentElem = new \DOMElement('div'));
            $contentElem->setAttribute('class', 'tab-content');

            $j = 0;
            foreach ($xpath->query(CssSelector::toXPath('ul > li'), $divElem) as $liElem) {
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