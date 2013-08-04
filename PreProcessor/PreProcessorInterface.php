<?php

namespace JMS\RstBundle\PreProcessor;

use Symfony\Component\Finder\SplFileInfo;

interface PreProcessorInterface
{
    /**
     * @param string $content the current content
     *
     * @return string the new content
     */
    public function prepare($content);
}