<?php

namespace CounterBundle\Tests\Repository;

use CounterBundle\Entity\Counter;
use CounterBundle\Repository\CounterRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(CounterRepository::class)]
#[RunTestsInSeparateProcesses]
final class CounterRepositoryIntegrationTest extends AbstractRepositoryTestCase
{
    private CounterRepository $repository;

    protected function onSetUp(): void
    {
        /** @var CounterRepository $repository */
        $repository = self::getContainer()->get(CounterRepository::class);
        $this->repository = $repository;
    }

    public function testSaveWithValidEntityPersistsToDatabase(): void
    {
        // Arrange
        $counter = $this->createTestCounter('test.counter');

        // Act
        self::getEntityManager()->persist($counter);
        self::getEntityManager()->flush();

        // Assert
        $this->assertNotNull($counter->getId());
        $savedCounter = $this->repository->find($counter->getId());
        $this->assertNotNull($savedCounter);
        $this->assertEquals('test.counter', $savedCounter->getName());
        $this->assertEquals(10, $savedCounter->getCount());
    }

    public function testFindOneByWithValidNameReturnsCounter(): void
    {
        // Arrange
        $counter = $this->createTestCounter('findable.counter');
        self::getEntityManager()->persist($counter);
        self::getEntityManager()->flush();

        // Act
        $result = $this->repository->findOneBy(['name' => 'findable.counter']);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('findable.counter', $result->getName());
        $this->assertEquals(10, $result->getCount());
    }

    public function testFindOneByWithNonExistentNameReturnsNull(): void
    {
        // Act
        $result = $this->repository->findOneBy(['name' => 'nonexistent.counter']);

        // Assert
        $this->assertNull($result);
    }

    public function testFindAllWithMultipleCountersReturnsAllCounters(): void
    {
        // Arrange
        $counter1 = $this->createTestCounter('counter.one');
        $counter2 = $this->createTestCounter('counter.two');
        self::getEntityManager()->persist($counter1);
        self::getEntityManager()->persist($counter2);
        self::getEntityManager()->flush();

        // Act
        $result = $this->repository->findAll();

        // Assert
        $this->assertGreaterThanOrEqual(2, count($result));
        $names = array_map(fn ($c) => $c->getName(), $result);
        $this->assertContains('counter.one', $names);
        $this->assertContains('counter.two', $names);
    }

    public function testCountWithEmptyResultsReturnsZero(): void
    {
        // Act - 使用一个不存在的条件来测试 count 方法
        $result = $this->repository->count(['name' => 'non.existent.counter.name.that.will.never.exist']);

        // Assert
        $this->assertEquals(0, $result);
    }

    public function testCountWithMultipleCountersReturnsCorrectCount(): void
    {
        // Arrange
        $initialCount = $this->repository->count([]);
        $counter1 = $this->createTestCounter('count.one');
        $counter2 = $this->createTestCounter('count.two');
        $counter3 = $this->createTestCounter('count.three');
        self::getEntityManager()->persist($counter1);
        self::getEntityManager()->persist($counter2);
        self::getEntityManager()->persist($counter3);
        self::getEntityManager()->flush();

        // Act
        $result = $this->repository->count([]);

        // Assert
        $this->assertEquals($initialCount + 3, $result);
    }

    public function testFindByWithCriteriaReturnsMatchingCounters(): void
    {
        // Arrange - Clear existing data first
        self::getEntityManager()->createQuery('DELETE FROM CounterBundle\Entity\Counter')->execute();

        $counter1 = $this->createTestCounter('find.counter', 100);
        $counter2 = $this->createTestCounter('another.counter', 100);
        $counter3 = $this->createTestCounter('different.counter', 200);
        self::getEntityManager()->persist($counter1);
        self::getEntityManager()->persist($counter2);
        self::getEntityManager()->persist($counter3);
        self::getEntityManager()->flush();

        // Act
        $result = $this->repository->findBy(['count' => 100]);

        // Assert
        $this->assertCount(2, $result);
        foreach ($result as $counter) {
            $this->assertEquals(100, $counter->getCount());
        }
    }

    public function testRemoveWithValidEntityDeletesFromDatabase(): void
    {
        // Arrange
        $counter = $this->createTestCounter('removable.counter');
        self::getEntityManager()->persist($counter);
        self::getEntityManager()->flush();
        $counterId = $counter->getId();

        // Act
        self::getEntityManager()->remove($counter);
        self::getEntityManager()->flush();

        // Assert
        $deletedCounter = $this->repository->find($counterId);
        $this->assertNull($deletedCounter);
    }

    public function testUpdateWithModifiedEntityPersistsChanges(): void
    {
        // Arrange
        $counter = $this->createTestCounter('updatable.counter');
        self::getEntityManager()->persist($counter);
        self::getEntityManager()->flush();

        // Act
        $counter->setCount(999);
        $counter->setContext(['updated' => true]);
        self::getEntityManager()->flush();

        // Assert
        self::getEntityManager()->clear();
        $updatedCounter = $this->repository->find($counter->getId());
        $this->assertNotNull($updatedCounter);
        $this->assertEquals(999, $updatedCounter->getCount());
        $this->assertEquals(['updated' => true], $updatedCounter->getContext());
    }

    private function createTestCounter(string $name, int $count = 10): Counter
    {
        $counter = new Counter();
        $counter->setName($name);
        $counter->setCount($count);
        $counter->setContext(['test' => true]);

        return $counter;
    }

    protected function getRepository(): CounterRepository
    {
        return self::getService(CounterRepository::class);
    }

    protected function createNewEntity(): object
    {
        $counter = new Counter();
        $counter->setName('test_counter_' . uniqid());
        $counter->setCount(10);
        $counter->setContext(['test' => true]);

        return $counter;
    }
}
