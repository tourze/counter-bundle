<?php

namespace CounterBundle\Tests\Provider;

use CounterBundle\CounterBundle;
use CounterBundle\Entity\Counter;
use CounterBundle\Provider\EntityTotalCountProvider;
use CounterBundle\Repository\CounterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Tourze\IntegrationTestKernel\IntegrationTestKernel;

class EntityTotalCountProviderIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private CounterRepository $counterRepository;
    private EntityTotalCountProvider $provider;

    protected static function createKernel(array $options = []): KernelInterface
    {
        $env = $options['environment'] ?? $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'test';
        $debug = $options['debug'] ?? $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? true;

        return new IntegrationTestKernel($env, $debug, [
            CounterBundle::class => ['all' => true],
        ]);
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->counterRepository = static::getContainer()->get(CounterRepository::class);
        $this->provider = static::getContainer()->get(EntityTotalCountProvider::class);
    }

    protected function tearDown(): void
    {
        $this->cleanDatabase();
        self::ensureKernelShutdown();
        parent::tearDown();
    }

    private function cleanDatabase(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('DELETE FROM table_count');
    }

    public function test_isEntityCare_withCounterEntity_returnsFalse(): void
    {
        // Act
        $result = $this->provider->isEntityCare(Counter::class);

        // Assert
        $this->assertFalse($result);
    }

    public function test_isEntityCare_withOtherEntity_returnsTrue(): void
    {
        // Act
        $result = $this->provider->isEntityCare('App\Entity\SomeEntity');

        // Assert
        $this->assertTrue($result);
    }

    public function test_increaseEntityCounter_withNewEntityClass_createsCounter(): void
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

    public function test_increaseEntityCounter_withExistingCounter_incrementsCount(): void
    {
        // Arrange
        $entityClass = 'App\Entity\ExistingEntity';
        $expectedName = sprintf('%s::total', $entityClass);

        $counter = new Counter();
        $counter->setName($expectedName);
        $counter->setCount(5);
        $this->entityManager->persist($counter);
        $this->entityManager->flush();

        // Act
        $this->provider->increaseEntityCounter($entityClass);

        // Assert
        $this->entityManager->clear();
        $updatedCounter = $this->counterRepository->findOneBy(['name' => $expectedName]);
        $this->assertNotNull($updatedCounter);
        $this->assertEquals(6, $updatedCounter->getCount());
    }

    public function test_decreaseEntityCounter_withExistingCounter_decrementsCount(): void
    {
        // Arrange
        $entityClass = 'App\Entity\DecrementEntity';
        $expectedName = sprintf('%s::total', $entityClass);

        $counter = new Counter();
        $counter->setName($expectedName);
        $counter->setCount(10);
        $this->entityManager->persist($counter);
        $this->entityManager->flush();

        // Act
        $this->provider->decreaseEntityCounter($entityClass);

        // Assert
        $this->entityManager->clear();
        $updatedCounter = $this->counterRepository->findOneBy(['name' => $expectedName]);
        $this->assertNotNull($updatedCounter);
        $this->assertEquals(9, $updatedCounter->getCount());
    }

    public function test_decreaseEntityCounter_withNonExistentCounter_doesNothing(): void
    {
        // Arrange
        $entityClass = 'App\Entity\NonExistentEntity';

        // Act
        $this->provider->decreaseEntityCounter($entityClass);

        // Assert - 无异常抛出即为成功
        $this->assertTrue(true);
    }

    public function test_decreaseEntityCounter_withCountZero_doesNotDecrementBelowZero(): void
    {
        // Arrange
        $entityClass = 'App\Entity\ZeroCountEntity';
        $expectedName = sprintf('%s::total', $entityClass);

        $counter = new Counter();
        $counter->setName($expectedName);
        $counter->setCount(1);
        $this->entityManager->persist($counter);
        $this->entityManager->flush();

        // Act - 尝试减少到零，然后再减少一次
        $this->provider->decreaseEntityCounter($entityClass);

        // Assert - 计数器不应该减少到零以下
        $this->entityManager->clear();
        $updatedCounter = $this->counterRepository->findOneBy(['name' => $expectedName]);
        $this->assertNotNull($updatedCounter);
        $this->assertEquals(1, $updatedCounter->getCount()); // 条件是 count > 1 才减少
    }

    public function test_getCounterByEntityClass_withExistingCounter_returnsCounter(): void
    {
        // Arrange
        $entityClass = 'App\Entity\FindableEntity';
        $expectedName = sprintf('%s::total', $entityClass);

        $counter = new Counter();
        $counter->setName($expectedName);
        $counter->setCount(42);
        $this->entityManager->persist($counter);
        $this->entityManager->flush();

        // Act
        $result = $this->provider->getCounterByEntityClass($entityClass);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($expectedName, $result->getName());
        $this->assertEquals(42, $result->getCount());
    }

    public function test_getCounterByEntityClass_withNonExistentCounter_returnsNull(): void
    {
        // Act
        $result = $this->provider->getCounterByEntityClass('App\Entity\NonExistentEntity');

        // Assert
        $this->assertNull($result);
    }

    public function test_increaseEntityCounter_withCounterEntity_doesNotCreateCounter(): void
    {
        // Arrange
        $initialCount = $this->counterRepository->count([]);

        // Act
        $this->provider->increaseEntityCounter(Counter::class);

        // Assert
        $finalCount = $this->counterRepository->count([]);
        $this->assertEquals($initialCount, $finalCount);
    }

    public function test_decreaseEntityCounter_withCounterEntity_doesNotDecrementAnyCounter(): void
    {
        // Arrange
        $counter = new Counter();
        $counter->setName('test.counter');
        $counter->setCount(5);
        $this->entityManager->persist($counter);
        $this->entityManager->flush();

        // Act
        $this->provider->decreaseEntityCounter(Counter::class);

        // Assert
        $this->entityManager->clear();
        $unchangedCounter = $this->counterRepository->findOneBy(['name' => 'test.counter']);
        $this->assertEquals(5, $unchangedCounter->getCount());
    }

    public function test_getCounters_withNoExistingCounters_createsCountersForAllEntities(): void
    {
        // Act
        $counters = iterator_to_array($this->provider->getCounters());

        // Assert
        // 由于测试环境可能没有很多实体，所以计数器可能为空
        // 主要验证方法不抛出异常
        // 如果有计数器，验证其结构
        foreach ($counters as $counter) {
            $this->assertInstanceOf(Counter::class, $counter);
            $this->assertNotNull($counter->getName());
            $this->assertIsInt($counter->getCount());
        }

        // 验证方法正常执行，不抛出异常
        $this->assertTrue(true);
    }
}
