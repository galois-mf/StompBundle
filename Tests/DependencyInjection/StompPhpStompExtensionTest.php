<?php

declare(strict_types=1);

namespace StompPhp\StompBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Stomp\StatefulStomp;
use StompPhp\StompBundle\DependencyInjection\StompPhpStompExtension;
use StompPhp\StompBundle\Stomp\Subscription;
use StompPhp\StompBundle\Tests\DependencyInjection\Mock\MockCallable;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class StompPhpStompExtensionTest extends TestCase
{
    public function testConnectionConfigHandling()
    {
        $clients = ['default', 'example_broker', 'simple_broker', 'broker_with_context'];
        $container = $this->getContainer('connections.yaml');
        foreach ($clients as $client) {
            $id = sprintf('stomp.clients.%s', $client);
            $this->assertTrue($container->has($id));
            $client = $container->get($id);
            self::assertInstanceOf(StatefulStomp::class, $client);
        }
    }

    public function testClientIsRegeneratedOnEachCall()
    {
        $container = $this->getContainer('connections.yaml');
        $clientA = $container->get('stomp.clients.default');
        $clientB = $container->get('stomp.clients.default');
        self::assertNotSame($clientA, $clientB);
    }

    public function testClientGetSslContext()
    {
        $container = $this->getContainer('connections.yaml');
        $stateful = $container->get('stomp.clients.broker_with_context');
        $connection = $stateful->getClient()->getConnection();
        $context = [
            'ssl' => [
                'local_cert' => 'cert.pem',
                'local_pk' => 'key.pem',
                'passphrase' => 'test',
                'cafile' => 'cert.ca',
            ]
        ];
        self::assertAttributeEquals($context, 'context', $connection);

    }
    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage Invalid configuration for path "stomp_php_stomp.clients.default": You can only define "broker_uri" or "host" and "port".
     */
    public function testInvalidConfiguredHostAndUri()
    {
        $this->getContainer('invalid_duplicated_host.yaml');
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage Invalid configuration for path "stomp_php_stomp.clients.default": No host configured, you need to define "host" and "port" or "broker_uri".
     */
    public function testInvalidConfiguredHost()
    {
        $this->getContainer('invalid_missing_port.yaml');
    }

    public function testConsumerConfigService()
    {
        $container = $this->getContainer('consumers.yaml');
        self::assertTrue($container->has('stomp.consumers.welcome'));
        $consumer = $container->get('stomp.consumers.welcome');
        self::assertInstanceOf(Subscription::class, $consumer);
        self::assertSame($consumer, $container->get('stomp.consumers.welcome'));
    }

    public function testConsumerConfigServiceMethod()
    {
        $container = $this->getContainer('consumers.yaml');
        self::assertTrue($container->has('stomp.consumers.welcome_with_method'));
        $consumer = $container->get('stomp.consumers.welcome_with_method');
        self::assertInstanceOf(Subscription::class, $consumer);
        self::assertSame($consumer, $container->get('stomp.consumers.welcome_with_method'));
    }

    private function getContainer(string $file): ContainerBuilder
    {
        $builder = new ContainerBuilder(new ParameterBag());
        $builder->registerExtension(new StompPhpStompExtension());

        $builder->setDefinition(
            MockCallable::class,
            new Definition(MockCallable::class)
        );

        $locator = new FileLocator(__DIR__.'/Fixtures');
        $loader = new YamlFileLoader($builder, $locator);
        $loader->load($file);

        $builder->compile();

        return $builder;
    }
}
