<?php

namespace CounterBundle\Provider;

use CounterBundle\Entity\Counter;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: 'app.counter.provider')]
interface CounterProvider
{
    /**
     * 获取所有可能的计数器
     * @return iterable<Counter>
     */
    public function getCounters(): iterable;
}
