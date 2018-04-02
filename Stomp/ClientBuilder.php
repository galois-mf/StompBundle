<?php

declare(strict_types=1);

namespace StompPhp\StompBundle\Stomp;

use Stomp\Client;
use Stomp\Network\Connection;
use Stomp\Network\Observer\HeartbeatEmitter;
use Stomp\StatefulStomp;

class ClientBuilder
{
    /**
     * @var string
     */
    private $host;
    /**
     * @var int
     */
    private $port;
    /**
     * @var string
     */
    private $user;
    /**
     * @var string
     */
    private $password;
    /**
     * @var string
     */
    private $vhost;
    /**
     * @var int
     */
    private $connectionTimeout;
    /**
     * @var int
     */
    private $writeTimeout;
    /**
     * @var int
     */
    private $readTimeoutMs;
    /**
     * @var int
     */
    private $heartbeatClientMs;
    /**
     * @var int
     */
    private $heartbeatServerMs;

    /**
     * @var string
     */
    private $brokerUri;

    /**
     * Sets options for this builder.
     *
     * @param array $options
     */
    public function setOptions(array $options): void
    {
        $keys = array_keys(get_object_vars($this));
        foreach ($options as $k => $v) {
            $k = preg_replace_callback(
                '/_\w/',
                function ($str) {
                    return strtoupper($str[0][1]);
                },
                $k
            );
            if (!in_array($k, $keys, true)) {
                throw new \InvalidArgumentException(sprintf('The parameter "%s" is not supported.', $k));
            }
            $this->$k = $v;
        }
    }

    /**
     * Returns the connection defined by this builder.
     *
     * @return StatefulStomp
     *
     * @throws \Stomp\Exception\ConnectionException
     */
    public function getClient(): StatefulStomp
    {
        $connection = $this->getConnection();

        $client = new Client($connection);
        if ($this->vhost) {
            $client->setVhostname($this->vhost);
        }
        if ($this->user || $this->password) {
            $client->setLogin($this->user, $this->password);
        }
        $this->configureHeartBeat($client);

        return new StatefulStomp($client);
    }

    /**
     * @return Connection
     *
     * @throws \Stomp\Exception\ConnectionException
     */
    private function getConnection(): Connection
    {
        $connection = new Connection($this->getConnectionString(), $this->getConnectTimeout());
        if ($this->writeTimeout) {
            $connection->setWriteTimeout($this->writeTimeout);
        }

        if ($this->readTimeoutMs) {
            $seconds = ($this->readTimeoutMs - ($this->readTimeoutMs % 1000)) / 1000;
            $microseconds = ($this->readTimeoutMs % 1000) * 1000;
            $connection->setReadTimeout($seconds, $microseconds);
        }

        return $connection;
    }

    private function getConnectionString(): string
    {
        if ($this->brokerUri) {
            return $this->brokerUri;
        }

        return sprintf('tcp://%s:%s', $this->host, $this->port);
    }

    private function getConnectTimeout(): int
    {
        return $this->connectionTimeout ?: 1;
    }

    private function configureHeartBeat(Client $client): void
    {
        if (!$this->heartbeatClientMs && !$this->heartbeatServerMs) {
            return;
        }
        $observer = new HeartbeatEmitter($client->getConnection());
        $client->setHeartbeat($this->heartbeatClientMs, $this->heartbeatServerMs);
        $client->getConnection()->getParser()->setObserver($observer);
    }
}
