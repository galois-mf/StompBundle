<?php

declare(strict_types=1);

namespace StompPhp\StompBundle\Stomp;

use Stomp\StatefulStomp;

class Subscription
{
    /**
     * @var StatefulStomp
     */
    private $client;
    /**
     * @var string
     */
    private $queue;
    /**
     * @var string
     */
    private $selector;
    /**
     * @var callable
     */
    private $service;

    /**
     * @var bool
     */
    private $stopRequested = false;

    /**
     * Subscription constructor.
     *
     * @param StatefulStomp $client
     * @param string        $queue
     * @param callable      $service
     */
    public function __construct(StatefulStomp $client, string $queue, callable $service)
    {
        $this->client = $client;
        $this->queue = $queue;
        $this->service = $service;
    }

    /**
     * @param string $selector
     */
    public function setSelector(?string $selector = null): void
    {
        $this->selector = $selector;
    }

    public function consume(): \Generator
    {
        $this->stopRequested = false;
        $subscriptionId = $this->client->subscribe($this->queue, $this->selector, 'client-individual');
        $service = $this->service;
        while (!$this->reachedEndCondition()) {
            if ($frame = $this->client->read()) {
                if (true === $service($frame)) {
                    $this->client->ack($frame);
                    yield true;
                } else {
                    $this->client->nack($frame);
                    yield false;
                }
            } else {
                yield null;
            }
        }
        $this->client->unsubscribe($subscriptionId);
    }

    private function reachedEndCondition()
    {
        return $this->stopRequested;
    }

    public function stop()
    {
        $this->stopRequested = true;
    }
}
