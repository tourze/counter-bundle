<?php

declare(strict_types=1);

namespace CounterBundle\DataFixtures;

use Carbon\CarbonImmutable;
use CounterBundle\Entity\Counter;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When(env: 'test')]
#[When(env: 'dev')]
class CounterFixtures extends Fixture implements FixtureGroupInterface
{
    public const COUNTER_REFERENCE_PREFIX = 'counter-';
    public const COUNTER_COUNT = 10;

    public function load(ObjectManager $manager): void
    {
        $entityTypes = [
            'App\Entity\User',
            'App\Entity\Article',
            'App\Entity\Product',
            'App\Entity\Order',
            'App\Entity\Category',
        ];

        for ($i = 0; $i < self::COUNTER_COUNT; ++$i) {
            $counter = new Counter();

            if ($i < count($entityTypes)) {
                $counter->setName($entityTypes[$i]);
                $counter->setCount(mt_rand(10, 1000));
                $counter->setContext(['entity_class' => $entityTypes[$i]]);
            } else {
                $counter->setName("custom_counter_{$i}");
                $counter->setCount(mt_rand(0, 100));
                $counter->setContext(['type' => 'custom']);
            }

            $now = CarbonImmutable::now();
            $counter->setCreateTime($now);
            $counter->setUpdateTime($now);

            $manager->persist($counter);
            $this->addReference(self::COUNTER_REFERENCE_PREFIX . $i, $counter);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return [
            'counter',
        ];
    }
}
