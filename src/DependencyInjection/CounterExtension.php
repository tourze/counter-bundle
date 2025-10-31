<?php

namespace CounterBundle\DependencyInjection;

use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

class CounterExtension extends AutoExtension
{
    protected function getConfigDir(): string
    {
        return __DIR__ . '/../Resources/config';
    }
}
