<?php

namespace JMS\RstBundle\LinkRewriter;

interface LinkRewriterInterface
{
    public function setCurrentFile($file);
    public function setPaths(array $paths);
    public function rewriteHref($href);
}