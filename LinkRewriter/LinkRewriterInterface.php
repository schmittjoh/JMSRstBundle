<?php

namespace JMS\RstBundle\LinkRewriter;

interface LinkRewriterInterface
{
    function setCurrentFile($file);
    function rewriteHref($href);
}