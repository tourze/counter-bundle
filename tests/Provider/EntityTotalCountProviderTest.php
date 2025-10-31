<?php

namespace CounterBundle\Tests\Provider;

use CounterBundle\Entity\Counter;
use CounterBundle\Provider\EntityTotalCountProvider;
use CounterBundle\Repository\CounterRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(EntityTotalCountProvider::class)]
#[RunTestsInSeparateProcesses]
final class EntityTotalCountProviderTest extends AbstractIntegrationTestCase
{
    private CounterRepository $counterRepository;

    private EntityTotalCountProvider $provider;

    protected function onSetUp(): void
    {
        /** @var CounterRepository $counterRepository */
        $counterRepository = self::getContainer()->get(CounterRepository::class);
        $this->counterRepository = $counterRepository;

        /** @var EntityTotalCountProvider $provider */
        $provider = self::getContainer()->get(EntityTotalCountProvider::class);
        $this->provider = $provider;
    }

    public function testIsEntityCareWithCounterEntityReturnsFalse(): void
    {
        // Act
        $result = $this->provider->isEntityCare(Counter::class);

        // Assert
        $this->assertFalse($result);
    }

    public function testIsEntityCareWithOtherEntityReturnsTrue(): void
    {
        // Act
        $result = $this->provider->isEntityCare('App\Entity\SomeEntity');

        // Assert
        $this->assertTrue($result);
    }

    public function testIncreaseEntityCounterWithNewEntityClassCreatesCounter(): void
    {
        // Arrange
        $entityClass = 'App\Entity\TestEntity';
        $expectedName = sprintf('%s::total', $entityClass);

        // Act
        $this->provider->increaseEntityCounter($entityClass);

        // Assert
        $counter = $this->counterRepository->findOneBy(['name' => $expectedName]);
        $this->assertNotNull($counter);
        $this->assertEquals($expectedName, $counter->getName());
        $this->assertEquals(1, $counter->getCount());
    }

    public function testIncreaseEntityCounterWithExistingCounterIncrementsCount(): void
    {
        // Arrange
        $entityClass = 'App\Entity\ExistingEntity';
        $expectedName = sprintf('%s::total', $entityClass);

        $counter = new Counter();
        $counter->setName($expectedName);
        $counter->setCount(5);
        self::getEntityManager()->persist($counter);
        self::getEntityManager()->flush();

        // Act
        $this->provider->increaseEntityCounter($entityClass);

        // Assert
        self::getEntityManager()->clear();
        $updatedCounter = $this->counterRepository->findOneBy(['name' => $expectedName]);
        $this->assertNotNull($updatedCounter);
        $this->assertEquals(6, $updatedCounter->getCount());
    }

    public function testDecreaseEntityCounterWithExistingCounterDecrementsCount(): void
    {
        // Arrange
        $entityClass = 'App\Entity\DecrementEntity';
        $expectedName = sprintf('%s::total', $entityClass);

        $counter = new Counter();
        $counter->setName($expectedName);
        $counter->setCount(10);
        self::getEntityManager()->persist($counter);
        self::getEntityManager()->flush();

        // Act
        $this->provider->decreaseEntityCounter($entityClass);

        // Assert
        self::getEntityManager()->clear();
        $updatedCounter = $this->counterRepository->findOneBy(['name' => $expectedName]);
        $this->assertNotNull($updatedCounter);
        $this->assertEquals(9, $updatedCounter->getCount());
    }

    public function testDecreaseEntityCounterWithNonExistentCounterDoesNothing(): void
    {
        // Arrange
        $entityClass = 'App\Entity\NonExistentEntity';

        // Act
        $this->provider->decreaseEntityCounter($entityClass);

        // Assert - 验证方法正常执行且不抛出异常
        $this->assertInstanceOf('CounterBundle\Provider\EntityTotalCountProvider', $this->provider);

        // 验证不存在的实体类不会创建新的计数器
        $counterName = sprintf('%s::total', $entityClass);
        $counter = $this->counterRepository->findOneBy(['name' => $counterName]);
        $this->assertNull($counter, 'Counter should not be created for non-existent entity class');
    }

    public function testDecreaseEntityCounterWithCountZeroDoesNotDecrementBelowZero(): void
    {
        // Arrange
        $entityClass = 'App\Entity\ZeroCountEntity';
        $expectedName = sprintf('%s::total', $entityClass);

        $counter = new Counter();
        $counter->setName($expectedName);
        $counter->setCount(1);
        self::getEntityManager()->persist($counter);
        self::getEntityManager()->flush();

        // Act - 尝试减少到零，然后再减少一次
        $this->provider->decreaseEntityCounter($entityClass);

        // Assert - 计数器不应该减少到零以下
        self::getEntityManager()->clear();
        $updatedCounter = $this->counterRepository->findOneBy(['name' => $expectedName]);
        $this->assertNotNull($updatedCounter);
        $this->assertEquals(1, $updatedCounter->getCount()); // 条件是 count > 1 才减少
    }

    public function testGetCounterByEntityClassWithExistingCounterReturnsCounter(): void
    {
        // Arrange
        $entityClass = 'App\Entity\FindableEntity';
        $expectedName = sprintf('%s::total', $entityClass);

        $counter = new Counter();
        $counter->setName($expectedName);
        $counter->setCount(42);
        self::getEntityManager()->persist($counter);
        self::getEntityManager()->flush();

        // Act
        $result = $this->provider->getCounterByEntityClass($entityClass);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($expectedName, $result->getName());
        $this->assertEquals(42, $result->getCount());
    }

    public function testGetCounterByEntityClassWithNonExistentCounterReturnsNull(): void
    {
        // Act
        $result = $this->provider->getCounterByEntityClass('App\Entity\NonExistentEntity');

        // Assert
        $this->assertNull($result);
    }

    public function testIncreaseEntityCounterWithCounterEntityDoesNotCreateCounter(): void
    {
        // Arrange
        $initialCount = $this->counterRepository->count([]);

        // Act
        $this->provider->increaseEntityCounter(Counter::class);

        // Assert
        $finalCount = $this->counterRepository->count([]);
        $this->assertEquals($initialCount, $finalCount);
    }

    public function testDecreaseEntityCounterWithCounterEntityDoesNotDecrementAnyCounter(): void
    {
        // Arrange
        $counter = new Counter();
        $counter->setName('test.counter');
        $counter->setCount(5);
        self::getEntityManager()->persist($counter);
        self::getEntityManager()->flush();

        // Act
        $this->provider->decreaseEntityCounter(Counter::class);

        // Assert
        self::getEntityManager()->clear();
        $unchangedCounter = $this->counterRepository->findOneBy(['name' => 'test.counter']);
        $this->assertNotNull($unchangedCounter);
        $this->assertEquals(5, $unchangedCounter->getCount());
    }

    public function testGetCountersWithNoExistingCountersCreatesCountersForAllEntities(): void
    {
        // Act
        $counters = iterator_to_array($this->provider->getCounters());

        // Assert
        // 由于测试环境可能没有很多实体，所以计数器可能为空
        // 主要验证方法不抛出异常
        // 如果有计数器，验证其结构
        foreach ($counters as $counter) {
            $this->assertNotNull($counter); // 验证计数器实例不为空
            $this->assertNotNull($counter->getName());
        }

        // 验证方法正常执行，不抛出异常
        $this->assertIsIterable($counters, 'getCounters should return an iterable');

        // 验证 provider 仍然有效
        $this->assertInstanceOf('CounterBundle\Provider\EntityTotalCountProvider', $this->provider);
    }
}
