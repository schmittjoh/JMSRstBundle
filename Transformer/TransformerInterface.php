<?php

namespace JMS\RstBundle\Transformer;

interface TransformerInterface
{
    function transform(\DOMDocument $document, \DOMXPath $xpath);
}