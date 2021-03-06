<?php

namespace JMS\RstBundle\Transformer;

/**
 * Embeds images directly into the HTML code.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ImageEmbeddingTransformer implements TransformerInterface
{
    public function transform(\DOMDocument $doc, \DOMXPath $xpath, $rootDir)
    {
        foreach ($xpath->query('//img') as $imgElem) {
            $src = $imgElem->getAttribute('src');
            if ( ! preg_match('#^(?:\.\./)*(_images/.*)#', $src, $match)) {
                continue;
            }
            $src = $match[1];

            if ( ! is_file($rootDir.'/'.$src)) {
                continue;
            }

            switch (true) {
                case '.jpg' === substr($src, -4):
                    $type = 'image/jpeg';
                    break;

                case '.png' === substr($src, -4):
                    $type = 'image/png';
                    break;

                case '.gif' === substr($src, -4):
                    $type = 'image/gif';
                    break;

                default:
                    throw new \RuntimeException(sprintf('Unsupported image type "%s".', $src));
            }

            $data = base64_encode(file_get_contents($rootDir.'/'.$src));
            $imgElem->setAttribute('src', 'data:'.$type.';base64,'.$data);
        }
    }
}