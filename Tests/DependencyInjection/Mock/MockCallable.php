<?php

declare(strict_types=1);

namespace StompPhp\StompBundle\Tests\DependencyInjection\Mock;

class MockCallable
{
    public function __invoke($message)
    {
        return true;
    }

    public function onMessage($message)
    {
        return true;
    }
}
