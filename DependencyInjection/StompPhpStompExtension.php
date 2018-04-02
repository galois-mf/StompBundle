<?php

declare(strict_types=1);

namespace StompPhp\StompBundle\DependencyInjection;

use Stomp\Client;
use StompPhp\StompBundle\Stomp\ClientFactory;
use StompPhp\StompBundle\Stomp\Subscription;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

class StompPhpStompExtension extends Extension
{
    public const CONSUMER_ID = 'stomp.consumers.%s';

    /**
     * Loads a specific configuration.
     *
     * @throws \InvalidArgumentException When provided tag is not defined in this extension
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $stompConfig = $this->processConfiguration(new Configuration(), $configs);
        $this->registerClients($stompConfig['clients'], $container);
        if (isset($stompConfig['consumers'])) {
            $this->registerConsumer($stompConfig['consumers'], $container);
        }
    }

    private function registerClients(array $config, ContainerBuilder $builder): void
    {
        foreach ($config as $name => $options) {
            $builder->setDefinition(
                sprintf('stomp.clients.%s', $name),
                $this->getClientDefinition($name, $options)
            );
        }
    }

    private function getClientDefinition(string $name, array $options): Definition
    {
        $public = $options['public'];
        unset($options['public']);

        return
            (new Definition(Client::class))
                ->setFactory(
                    [
                        ClientFactory::class,
                        'newClient',
                    ]
                )->setArguments([$options])
                ->setPublic($public)
                ->setShared(false)
                ->addTag('stomp.client', ['name' => $name]);
    }

    private function registerConsumer(array $config, ContainerBuilder $builder): void
    {
        foreach ($config as $name => $options) {
            $builder->setDefinition(
                sprintf('stomp.consumers.%s', $name),
                $this->getConsumerDefinition($builder, $name, $options)
            );
        }
    }

    private function getConsumerDefinition(ContainerBuilder $container, string $name, array $options): Definition
    {
        return
            (new Definition(Subscription::class))
                ->setArguments(
                    [
                        new Reference('stomp.clients.'.$options['client']),
                        $options['queue'],
                        $this->getServiceCallable($options),
                    ]
                )
                ->addMethodCall('setSelector', [$options['selector']])
                ->setPublic(true)
                ->setShared(true)
                ->addTag('stomp.subscription', ['name' => $name]);
    }

    private function getServiceCallable(array $options)
    {
        if ($options['service_method']) {
            return [new Reference($options['service']), $options['service_method']];
        }

        return new Reference($options['service']);
    }
}
