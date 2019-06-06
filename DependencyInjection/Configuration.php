<?php

declare(strict_types=1);

namespace StompPhp\StompBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $tree = new TreeBuilder('stomp_php_stomp');

        if (method_exists($tree, 'getRootNode')) {
            $root = $tree->getRootNode();
        } else {
            // BC layer for symfony/config 4.1 and older
            $root = $tree->root('stomp_php_stomp');
        }

        $this->addConnections($root);
        $this->addConsumers($root);

        return $tree;
    }

    private function addConnections(ArrayNodeDefinition $node): void
    {
        $node->fixXmlConfig('connection')
            ->children()
                ->arrayNode('clients')
                ->useAttributeAsKey('key')
                    ->prototype('array')
                        ->validate()
                            ->ifTrue(
                                function (array $config) {
                                    return $config['broker_uri'] && ($config['host'] || $config['port']);
                                }
                             )
                             ->thenInvalid('You can only define "broker_uri" or "host" and "port".')
                        ->end()
                        ->validate()
                            ->ifTrue(
                                function (array $config) {
                                    return !$config['broker_uri'] && (!$config['host'] || !$config['port']);
                                }
                             )
                             ->thenInvalid('No host configured, you need to define "host" and "port" or "broker_uri".')
                        ->end()
                        ->children()

                            ->scalarNode('broker_uri')->defaultValue(null)
                                ->example(
                                    [
                                        'tcp://localhost:61614',
                                        'failover://(tcp://localhost:61614,ssl://localhost:61612)',
                                        'failover://(tcp://localhost:61614,ssl://localhost:61612)?randomize=true',
                                    ]
                                )
                            ->end()
                            ->scalarNode('host')->defaultValue(null)->end()
                            ->scalarNode('port')->defaultValue(null)->end()
                            ->scalarNode('user')->defaultValue(null)->end()
                            ->scalarNode('password')->defaultValue(null)->end()
                            ->scalarNode('vhost')->defaultValue('/')->end()
                            ->scalarNode('connection_timeout')
                                ->info('Initial connect timeout.')
                                ->defaultValue(1)
                            ->end()
                            ->scalarNode('write_timeout')
                                ->info('Timeout (seconds) for write operations.')
                                ->defaultValue(null)
                            ->end()
                            ->scalarNode('read_timeout_ms')
                                ->info('Timeout (milliseconds) for waiting for new data within an read operation.')
                                ->defaultValue(null)
                            ->end()
                            ->scalarNode('heartbeat_client_ms')
                                ->info('Heartbeat interval (milliseconds) guaranteed from client.')
                                ->defaultValue(0)
                            ->end()
                            ->scalarNode('heartbeat_server_ms')
                                ->info('Heartbeat interval (milliseconds) requested from server.')
                                ->defaultValue(0)
                            ->end()
                            ->scalarNode('public')
                                ->info('Make this client available as public service.')
                                ->defaultValue(false)
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function addConsumers(ArrayNodeDefinition $node): void
    {
        $node
            ->fixXmlConfig('consumer')
            ->children()
                ->arrayNode('consumers')
                    ->canBeUnset()
                    ->useAttributeAsKey('key')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('client')->defaultValue('default')->end()
                            ->scalarNode('service')
                                ->info('An invokable service that receives the frames from the queue.')
                                ->isRequired()
                            ->end()
                            ->scalarNode('service_method')
                                ->info('The method of the service that should receive the message.')
                                ->defaultValue(null)
                            ->end()
                            ->scalarNode('queue')->isRequired()->end()
                            ->scalarNode('selector')
                                ->info('The selector argument for the subscription.')
                                ->defaultValue(null)
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
