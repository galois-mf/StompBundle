<?php

declare(strict_types=1);

namespace StompPhp\StompBundle\Tests\Stomp;

use PHPUnit\Framework\TestCase;
use Stomp\Client;
use Stomp\Network\Connection;
use Stomp\Network\Observer\HeartbeatEmitter;
use Stomp\StatefulStomp;
use StompPhp\StompBundle\Stomp\ClientBuilder;

class ClientBuilderTest extends TestCase
{
    /**
     * @param array         $options
     * @param StatefulStomp $expected
     *
     * @throws \Stomp\Exception\ConnectionException
     *
     * @dataProvider configProvider
     */
    public function testBuilder(array $options, StatefulStomp $expected)
    {
        $builder = new ClientBuilder();
        $builder->setOptions($options);
        self::assertEquals($expected, $builder->getClient());
    }

    /**
     * @expectedExceptionMessage The parameter "someParameter" is not supported.
     * @expectedException \InvalidArgumentException
     */
    public function testBuilderFailsIfPropertyIsNotKnown()
    {
        $builder = new ClientBuilder();
        $builder->setOptions(['some_parameter' => true]);
    }

    public function configProvider(): array
    {
        $fullExpected = new StatefulStomp(new Client(new Connection('tcp://127.0.0.1:256', 10)));
        $fullExpected->getClient()->setHeartbeat(550, 350);
        $fullExpected->getClient()->getConnection()->setWriteTimeout(5);
        $fullExpected->getClient()->getConnection()->setReadTimeout(6, 750000);
        $fullExpected->getClient()->setLogin('user', 'password');
        $fullExpected->getClient()->setVhostname('/someHost');
        $fullExpectedHeartBeat = new HeartbeatEmitter($fullExpected->getClient()->getConnection());
        $fullExpected->getClient()->getConnection()->getParser()->setObserver($fullExpectedHeartBeat);

        return [
            'simple' => [
                [
                    'broker_uri' => 'tcp://127.0.0.1:1515',
                ],
                (new StatefulStomp(new Client(new Connection('tcp://127.0.0.1:1515')))),
            ],
            'exploded' => [
                [
                    'host' => '127.0.0.1',
                    'port' => 1515,
                ],
                (new StatefulStomp(new Client(new Connection('tcp://127.0.0.1:1515')))),
            ],
            'full' => [
                [
                    'broker_uri' => 'tcp://127.0.0.1:256',
                    'vhost' => '/someHost',
                    'user' => 'user',
                    'password' => 'password',
                    'write_timeout' => 5,
                    'read_timeout_ms' => 6750,
                    'connection_timeout' => 10,
                    'heartbeat_client_ms' => 550,
                    'heartbeat_server_ms' => 350,
                ],
                $fullExpected,
            ],
        ];
    }
}
