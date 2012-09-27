<?php

namespace JMS\RstBundle\DependencyInjection;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $exFinder = new ExecutableFinder();
        $tb = new TreeBuilder();

        $tb
            ->root('jms_rst', 'array')
                ->children()
                    ->scalarNode('sphinx_path')
                        ->validate()
                            ->always(function($v) {
                                exec($v.' --help', $output, $returnVar);

                                if (0 !== $returnVar) {
                                    throw new \Exception(sprintf("The given sphinx-build path '%s' is correct:\n\n%s", $v, implode("\n", $output)));
                                }

                                return $v;
                            })
                        ->end()
                        ->defaultValue(function() use ($exFinder) { return $exFinder->find('sphinx-build'); })
                    ->end()
                    ->scalarNode('sphinx_config_dir')
                        ->isRequired()
                        ->validate()
                            ->always(function($v) {
                                if ( ! is_dir($v)) {
                                    throw new \RuntimeException(sprintf('The config dir "%s" does not exist.', $v));
                                }

                                return $v;
                            })
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $tb;
    }
}