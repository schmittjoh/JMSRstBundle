<?php

namespace JMS\RstBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class JMSRstExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $config = $this->processConfiguration($this->getConfiguration(array(), $container), $configs);

        $container->setParameter('jms_rst.sphinx_path', $config['sphinx_path']);
    }
}