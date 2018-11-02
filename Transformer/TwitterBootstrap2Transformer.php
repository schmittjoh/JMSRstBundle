<?php

namespace JMS\RstBundle\Transformer;

use PhpOption\None;
use PhpOption\Some;
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

        $this->rewriteLiterals($doc, $xpath);

        $this->rewriteSubheaders($doc, $xpath);

        $this->rewriteAdmonitions($doc, $xpath, 'note', null, 'icon-pencil');
        $this->rewriteAdmonitions($doc, $xpath, 'tip', 'alert-info', 'icon-eye-open');
        $this->rewriteAdmonitions($doc, $xpath, 'warning', 'alert-error', 'icon-warning-sign');

        $this->rewriteVersion($doc, $xpath, 'versionadded', 'label-success');

        $this->rewriteBlockquotes($doc, $xpath);
    }

    private function rewriteSubheaders(\DOMDocument $document, \DOMXPath $xpath)
    {
        foreach ($xpath->query($this->cssSelector->toXPath('h1 > em, h2 > em, h3 > em, h4 > em, h5 > em, h6 > em')) as $emElem) {
            /** @var \DOMElement $emElem */

            $smallElem = $document->createElement('small');
            foreach ($emElem->childNodes as $childNode) {
                $smallElem->appendChild($childNode);
            }

            $emElem->parentNode->insertBefore($smallElem, $emElem);
            $emElem->parentNode->removeChild($emElem);
        }
    }

    private function rewriteLiterals(\DOMDocument $doc, \DOMXPath $xpath)
    {
        foreach ($xpath->query($this->cssSelector->toXPath('tt.docutils.literal')) as $ttElem) {
            /** @var \DOMElement $ttElem */

            $this->getCodeFromLiteralMaybe($ttElem)
                ->forAll(function($code) use ($ttElem, $doc) {
                    $preElem = $doc->createElement('code');
                    $preElem->appendChild($doc->createTextNode($code));

                    $ttElem->parentNode->insertBefore($preElem, $ttElem);
                    $ttElem->parentNode->removeChild($ttElem);
                })
            ;
        }
    }

    private function getCodeFromLiteralMaybe(\DOMElement $ttElem)
    {
        if ($ttElem->childNodes->length === 0) {
            return None::create();
        }

        $code = '';
        foreach ($ttElem->childNodes as $childElem) {
            if ($childElem instanceof \DOMText) {
                $code .= $childElem->textContent;
                continue;
            }

            if ( ! $childElem instanceof \DOMElement) {
                return None::create();
            }

            if ($childElem->nodeName !== 'span' || $childElem->getAttribute('class') !== 'pre') {
                return None::create();
            }

            $code .= $childElem->textContent;
        }

        return new Some($code);
    }

    private function rewriteBlockquotes(\DOMDocument $doc, \DOMXPath $xpath)
    {
        foreach ($xpath->query($this->cssSelector->toXPath('blockquote > div')) as $divElem) {
            /** @var $divElem \DOMElement */

            $quoteElem = $divElem->parentNode;
            for ($i=0; $i<$divElem->childNodes->length;$i++) {
                $childNode = $divElem->childNodes->item($i);

                if ($childNode instanceof \DOMText) {
                    if ('' === trim($childNode->nodeValue)) {
                        continue;
                    }

                    $quoteElem->appendChild($doc->createElement('p', $childNode->nodeValue));

                    continue;
                }

                if ('attribution' === (string) $childNode->getAttribute('class')) {
                    $attrNode = $doc->createElement('small');

                    for ($k=1; $k<$childNode->childNodes->length; $k++) {
                        $attrChild = $childNode->childNodes->item($k);
                        $attrNode->appendChild($attrChild);
                    }

                    $quoteElem->appendChild($attrNode);

                    continue;
                }

                $quoteElem->appendChild($childNode);
            }

            $quoteElem->removeChild($divElem);
        }
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