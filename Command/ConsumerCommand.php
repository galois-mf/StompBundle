<?php

declare(strict_types=1);

namespace StompPhp\StompBundle\Command;

use StompPhp\StompBundle\DependencyInjection\StompPhpStompExtension;
use StompPhp\StompBundle\Stomp\Subscription;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ConsumerCommand extends Command implements ContainerAwareInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;
    /**
     * @var Subscription
     */
    private $subscription;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        $maxMessages = $input->getOption('messages');

        $messages = 0;
        $this->subscription = $this->getSubscription($input->getArgument('name'));
        $signalHandler = $this->registerSignalHandler();
        foreach ($this->subscription->consume() as $unit) {
            if (null !== $unit) {
                ++$messages;
                if ($output->isVerbose()) {
                    $output->writeln(sprintf('Message #%s %s', $messages, ($unit ? 'processed' : 'skipped')));
                }
                if ($maxMessages && $messages == $maxMessages) {
                    $this->subscription->stop();
                }
            } elseif ($output->isVeryVerbose()) {
                $output->writeln('No message available');
            }

            if ($signalHandler) {
                pcntl_signal_dispatch();
            }
        }

        return 0;
    }

    private function getSubscription(string $name): Subscription
    {
        $id = sprintf(StompPhpStompExtension::CONSUMER_ID, $name);
        $subscription = $this->container->get($id);
        if (!is_a($subscription, Subscription::class)) {
            throw new \LogicException(sprintf('The service "%s" is not a "%s".', $id, Subscription::class));
        }

        return $subscription;
    }

    private function registerSignalHandler(): bool
    {
        if (extension_loaded('pcntl') && function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'stopSubscription']);
            pcntl_signal(SIGINT, [$this, 'stopSubscription']);
            return true;
        }
        return false;
    }

    protected function configure()
    {
        $this->setName('stomp:consumer')
            ->setDescription('Start a process for the given consumer.')
            ->addArgument('name', InputArgument::REQUIRED, 'Consumer Name')
            ->addOption('messages', 'm', InputOption::VALUE_OPTIONAL, 'Amount of messages to consume.', 0)
            ->addOption('selector', 's', InputOption::VALUE_OPTIONAL, 'Selector (override)', null);
    }

    private function stopSubscription(): void
    {
        if ($this->subscription) {
            $this->subscription->stop();
        }
    }
}
