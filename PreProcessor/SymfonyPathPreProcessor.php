<?php

namespace JMS\RstBundle\PreProcessor;

use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Routing\RouterInterface;

class SymfonyPathPreProcessor implements PreProcessorInterface
{
    private $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function prepare($content)
    {
        if (false === strpos($content, ':sf_path:')) {
            return $content;
        }

        return preg_replace_callback('/:sf_path:`([^<]+)<([^>]+)>`/', function ($match) {
            return '`'.$match[1].'<'.$this->router->generate($match[2], array(), true).'>`_';
        }, $content);
    }
}