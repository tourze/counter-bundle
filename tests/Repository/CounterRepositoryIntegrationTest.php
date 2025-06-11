<?php

namespace CounterBundle\Tests\Repository;

use CounterBundle\CounterBundle;
use CounterBundle\Entity\Counter;
use CounterBundle\Repository\CounterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Tourze\IntegrationTestKernel\IntegrationTestKernel;

class CounterRepositoryIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private CounterRepository $repository;

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
        $this->repository = static::getContainer()->get(CounterRepository::class);
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

    public function test_save_withValidEntity_persistsToDatabase(): void
    {
        // Arrange
        $counter = $this->createTestCounter('test.counter');

        // Act
        $this->entityManager->persist($counter);
        $this->entityManager->flush();

        // Assert
        $this->assertNotNull($counter->getId());
        $savedCounter = $this->repository->find($counter->getId());
        $this->assertNotNull($savedCounter);
        $this->assertEquals('test.counter', $savedCounter->getName());
        $this->assertEquals(10, $savedCounter->getCount());
    }

    public function test_findOneBy_withValidName_returnsCounter(): void
    {
        // Arrange
        $counter = $this->createTestCounter('findable.counter');
        $this->entityManager->persist($counter);
        $this->entityManager->flush();

        // Act
        $result = $this->repository->findOneBy(['name' => 'findable.counter']);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('findable.counter', $result->getName());
        $this->assertEquals(10, $result->getCount());
    }

    public function test_findOneBy_withNonExistentName_returnsNull(): void
    {
        // Act
        $result = $this->repository->findOneBy(['name' => 'nonexistent.counter']);

        // Assert
        $this->assertNull($result);
    }

    public function test_findAll_withMultipleCounters_returnsAllCounters(): void
    {
        // Arrange
        $counter1 = $this->createTestCounter('counter.one');
        $counter2 = $this->createTestCounter('counter.two');
        $this->entityManager->persist($counter1);
        $this->entityManager->persist($counter2);
        $this->entityManager->flush();

        // Act
        $result = $this->repository->findAll();

        // Assert
        $this->assertCount(2, $result);
        $names = array_map(fn($c) => $c->getName(), $result);
        $this->assertContains('counter.one', $names);
        $this->assertContains('counter.two', $names);
    }

    public function test_count_withEmptyDatabase_returnsZero(): void
    {
        // Act
        $result = $this->repository->count([]);

        // Assert
        $this->assertEquals(0, $result);
    }

    public function test_count_withMultipleCounters_returnsCorrectCount(): void
    {
        // Arrange
        $counter1 = $this->createTestCounter('count.one');
        $counter2 = $this->createTestCounter('count.two');
        $counter3 = $this->createTestCounter('count.three');
        $this->entityManager->persist($counter1);
        $this->entityManager->persist($counter2);
        $this->entityManager->persist($counter3);
        $this->entityManager->flush();

        // Act
        $result = $this->repository->count([]);

        // Assert
        $this->assertEquals(3, $result);
    }

    public function test_findBy_withCriteria_returnsMatchingCounters(): void
    {
        // Arrange
        $counter1 = $this->createTestCounter('find.counter', 100);
        $counter2 = $this->createTestCounter('another.counter', 100);
        $counter3 = $this->createTestCounter('different.counter', 200);
        $this->entityManager->persist($counter1);
        $this->entityManager->persist($counter2);
        $this->entityManager->persist($counter3);
        $this->entityManager->flush();

        // Act
        $result = $this->repository->findBy(['count' => 100]);

        // Assert
        $this->assertCount(2, $result);
        foreach ($result as $counter) {
            $this->assertEquals(100, $counter->getCount());
        }
    }

    public function test_remove_withValidEntity_deletesFromDatabase(): void
    {
        // Arrange
        $counter = $this->createTestCounter('removable.counter');
        $this->entityManager->persist($counter);
        $this->entityManager->flush();
        $counterId = $counter->getId();

        // Act
        $this->entityManager->remove($counter);
        $this->entityManager->flush();

        // Assert
        $deletedCounter = $this->repository->find($counterId);
        $this->assertNull($deletedCounter);
    }

    public function test_update_withModifiedEntity_persistsChanges(): void
    {
        // Arrange
        $counter = $this->createTestCounter('updatable.counter');
        $this->entityManager->persist($counter);
        $this->entityManager->flush();

        // Act
        $counter->setCount(999);
        $counter->setContext(['updated' => true]);
        $this->entityManager->flush();

        // Assert
        $this->entityManager->clear();
        $updatedCounter = $this->repository->find($counter->getId());
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
}
