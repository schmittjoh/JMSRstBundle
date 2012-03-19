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
                ->end()
            ->end()
        ;

        return $tb;
    }
}