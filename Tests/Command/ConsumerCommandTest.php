<?php

declare(strict_types=1);

namespace StompPhp\StompBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Stomp\StatefulStomp;
use Stomp\Transport\Frame;
use StompPhp\StompBundle\Command\ConsumerCommand;
use StompPhp\StompBundle\DependencyInjection\StompPhpStompExtension;
use StompPhp\StompBundle\Stomp\Subscription;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class ConsumerCommandTest extends TestCase
{
    public function testCommand()
    {
        $container = new Container(new ParameterBag());

        $stomp = $this->createMock(StatefulStomp::class);
        $stomp->expects($this->any())->method('read')->willReturnOnConsecutiveCalls(
            new Frame(),
            false,
            new Frame(),
            false,
            new Frame()
        );

        $results = [
            true, false, true,
        ];
        $service = function () use (&$results) {
            return array_shift($results);
        };
        $subscription = new Subscription(
            $stomp, 'some-queue', $service
        );
        $container->set(sprintf(StompPhpStompExtension::CONSUMER_ID, 'test_consumer'), $subscription);

        $command = new ConsumerCommand();
        $command->setContainer($container);

        $output = new BufferedOutput();
        $output->setVerbosity(Output::VERBOSITY_DEBUG);
        $command->run(new ArrayInput(['name' => 'test_consumer', '--messages' => 3], $command->getDefinition()), $output);

        $content = $output->fetch();
        $expected = 'Message #1 processed'.PHP_EOL;
        $expected .= 'No message available'.PHP_EOL;
        $expected .= 'Message #2 skipped'.PHP_EOL;
        $expected .= 'No message available'.PHP_EOL;
        $expected .= 'Message #3 processed'.PHP_EOL;
        self::assertEquals($expected, $content);
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage The service "stomp.consumers.some-service" is not a "StompPhp\StompBundle\Stomp\Subscription".
     */
    public function testCommandVerifiesServiceInstance()
    {
        $container = new Container(new ParameterBag());

        $container->set(sprintf(StompPhpStompExtension::CONSUMER_ID, 'some-service'), __CLASS__);

        $command = new ConsumerCommand();
        $command->setContainer($container);

        $output = new BufferedOutput();
        $output->setVerbosity(Output::VERBOSITY_DEBUG);
        $command->run(new ArrayInput(['name' => 'some-service'], $command->getDefinition()), $output);
    }
}
