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
        $container->setParameter('jms_rst.sphinx_config_dir', $config['sphinx_config_dir']);

        $container->register('jms_rst.project_generator', 'JMS\RstBundle\Generator\ProjectGenerator')
            ->setAbstract(true)
            ->addArgument('%jms_rst.sphinx_path%')
            ->addArgument('%jms_rst.sphinx_config_dir%')
        ;
    }
}