<?php

namespace CounterBundle\Provider;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.counter.provider')]
interface CounterProvider
{
    /**
     * 获取所有可能的计数器
     */
    public function getCounters(): iterable;
}
