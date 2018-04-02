<?php

declare(strict_types=1);

namespace StompPhp\StompBundle\Tests\Stomp;

use PHPUnit\Framework\TestCase;
use Stomp\StatefulStomp;
use Stomp\Transport\Frame;
use StompPhp\StompBundle\Stomp\Subscription;

class SubscriptionTest extends TestCase
{
    public function testSubscriptionHandling()
    {
        $expectedValues = [
            new Frame(),
            new Frame(),
            new Frame(),
        ];

        $expectedProcessing = [
            true,
            false,
            true,
        ];

        $reads = [
            false,
            false,
            $expectedValues[0],
            $expectedValues[1],
            false,
            $expectedValues[2],
            false,
        ];

        $expectedReturns = [
            null,
            null,
            true,
            false,
            null,
            true,
            false,
        ];

        $client = $this->createMock(StatefulStomp::class);
        $client->expects($this->once())->method('subscribe')->willReturn('test-1');
        $client->expects($this->once())->method('unsubscribe')->with('test-1');
        $client->expects($this->any())->method('read')->willReturnOnConsecutiveCalls(...$reads);

        $callable = function ($value) use (&$expectedValues, &$expectedProcessing) {
            self::assertSame(array_shift($expectedValues), $value);

            return array_shift($expectedProcessing);
        };

        $rounds = 0;

        $instance = new Subscription($client, 'myQueue', $callable);
        foreach ($instance->consume() as $state) {
            self::assertSame(array_shift($expectedReturns), $state);
            ++$rounds;
            if (6 == $rounds) {
                $instance->stop();
            }
        }
    }
}
