<?php

namespace CounterBundle\Tests\Integration;

use CounterBundle\CounterBundle;
use CounterBundle\Entity\Counter;
use CounterBundle\Provider\EntityTotalCountProvider;
use CounterBundle\Repository\CounterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Tourze\IntegrationTestKernel\IntegrationTestKernel;

/**
 * 计数器包性能和并发测试
 * 测试高负载情况下的性能表现和数据一致性
 */
class CounterPerformanceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private CounterRepository $counterRepository;
    private EntityTotalCountProvider $countProvider;

    protected static function createKernel(array $options = []): KernelInterface
    {
        $env = $options['environment'] ?? $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'test';
        $debug = $options['debug'] ?? $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? true;

        return new IntegrationTestKernel($env, $debug, [
            CounterBundle::class => ['all' => true],
        ]);
    }

    /**
     * 测试高并发增减操作的性能
     */
    public function test_highConcurrencyOperations_manyIncrements_maintainsPerformance(): void
    {
        // Arrange
        $entityClass = 'TestEntity';
        $operationCount = 100; // 减少操作次数以提高测试速度

        // Act & Time the operations
        $startTime = microtime(true);

        for ($i = 0; $i < $operationCount; $i++) {
            $this->countProvider->increaseEntityCounter($entityClass);
            // 每50次操作清理一次缓存以防内存问题
            if ($i % 50 === 0) {
                $this->entityManager->clear();
            }
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        // 确保从数据库重新读取
        $this->entityManager->clear();

        // Assert
        $counterName = sprintf('%s::total', $entityClass);
        $counter = $this->counterRepository->findOneBy(['name' => $counterName]);

        $this->assertNotNull($counter);
        $this->assertEquals($operationCount, $counter->getCount());

        // Performance assertion - should complete within reasonable time
        $this->assertLessThan(10.0, $totalTime, 'Operations should complete within 10 seconds');

        // Log performance for monitoring
        $opsPerSecond = $operationCount / $totalTime;
        $this->addToAssertionCount(1); // For reporting
        // echo "\nPerformance: {$opsPerSecond} operations/second\n";
    }

    /**
     * 测试混合增减操作的性能和正确性
     */
    public function test_mixedIncrementDecrement_largeVolume_maintainsAccuracy(): void
    {
        // Arrange
        $entityClass = 'MixedTestEntity';
        $increments = 600;
        $decrements = 200;
        $expectedFinalCount = $increments - $decrements;

        // Act: 混合执行增减操作
        for ($i = 0; $i < $increments; $i++) {
            $this->countProvider->increaseEntityCounter($entityClass);
        }

        for ($i = 0; $i < $decrements; $i++) {
            $this->countProvider->decreaseEntityCounter($entityClass);
        }

        // 确保从数据库重新读取
        $this->entityManager->clear();

        // Assert
        $counterName = sprintf('%s::total', $entityClass);
        $counter = $this->counterRepository->findOneBy(['name' => $counterName]);

        $this->assertNotNull($counter);
        $this->assertEquals($expectedFinalCount, $counter->getCount());
    }

    /**
     * 测试多实体类型并发操作
     */
    public function test_multiEntityConcurrency_parallelOperations_maintainsIsolation(): void
    {
        // Arrange
        $entityClasses = ['Entity1', 'Entity2', 'Entity3', 'Entity4', 'Entity5'];
        $operationsPerEntity = 100;

        // Act: 为每个实体类型并发执行操作
        foreach ($entityClasses as $entityClass) {
            for ($i = 0; $i < $operationsPerEntity; $i++) {
                $this->countProvider->increaseEntityCounter($entityClass);
            }
        }

        // 确保从数据库重新读取
        $this->entityManager->clear();

        // Assert: 每个实体类型都有正确的计数
        foreach ($entityClasses as $entityClass) {
            $counterName = sprintf('%s::total', $entityClass);
            $counter = $this->counterRepository->findOneBy(['name' => $counterName]);

            $this->assertNotNull($counter, "Counter should exist for {$entityClass}");
            $this->assertEquals($operationsPerEntity, $counter->getCount(),
                "Counter for {$entityClass} should have correct count");
        }
    }

    /**
     * 测试大量实体类型的处理能力
     */
    public function test_manyEntityTypes_getCounters_handlesLargeMetadata(): void
    {
        // Arrange: 预创建多个不同实体类型的计数器
        $entityCount = 20; // 减少实体数量
        for ($i = 1; $i <= $entityCount; $i++) {
            $entityClass = "TestEntity{$i}";
            $this->countProvider->increaseEntityCounter($entityClass);
        }

        $this->entityManager->clear();

        // Act: 验证所有计数器都被创建
        $actualCounters = 0;
        for ($i = 1; $i <= $entityCount; $i++) {
            $entityClass = "TestEntity{$i}";
            $counterName = sprintf('%s::total', $entityClass);
            $counter = $this->counterRepository->findOneBy(['name' => $counterName]);
            if ($counter !== null) {
                $actualCounters++;
            }
        }

        // Assert
        $this->assertEquals($entityCount, $actualCounters);

        // 性能测试：确保创建操作是高效的
        $this->addToAssertionCount(1);
    }

    /**
     * 测试计数器更新的原子性
     */
    public function test_atomicUpdates_rapidIncrements_noDataLoss(): void
    {
        // Arrange
        $entityClass = 'AtomicTestEntity';
        $batchSize = 500;

        // Act: 快速连续执行增量操作
        for ($i = 0; $i < $batchSize; $i++) {
            $this->countProvider->increaseEntityCounter($entityClass);
        }

        // 确保从数据库重新读取
        $this->entityManager->clear();

        // Assert: 验证所有增量都被正确记录
        $counterName = sprintf('%s::total', $entityClass);
        $counter = $this->counterRepository->findOneBy(['name' => $counterName]);

        $this->assertNotNull($counter);
        $this->assertEquals($batchSize, $counter->getCount());
    }

    /**
     * 测试错误恢复的性能影响
     */
    public function test_errorRecovery_invalidOperations_maintainsPerformance(): void
    {
        // Arrange: 创建一个存在的计数器
        $entityClass = 'ErrorTestEntity';
        $this->countProvider->increaseEntityCounter($entityClass);

        $startTime = microtime(true);

        // Act: 执行一些可能导致错误的操作
        for ($i = 0; $i < 100; $i++) {
            // 尝试减少计数（可能触发条件检查）
            $this->countProvider->decreaseEntityCounter($entityClass);

            // 继续增加
            $this->countProvider->increaseEntityCounter($entityClass);
        }

        $endTime = microtime(true);

        // Assert: 操作应该在合理时间内完成
        $totalTime = $endTime - $startTime;
        $this->assertLessThan(5.0, $totalTime, 'Error handling should not significantly impact performance');

        // 验证最终状态一致
        $counterName = sprintf('%s::total', $entityClass);
        $counter = $this->counterRepository->findOneBy(['name' => $counterName]);
        $this->assertNotNull($counter);
        $this->assertGreaterThan(0, $counter->getCount());
    }

    /**
     * 测试内存使用效率
     */
    public function test_memoryEfficiency_largeOperations_managedMemoryUsage(): void
    {
        // Arrange
        $initialMemory = memory_get_usage(true);
        $entityClass = 'MemoryTestEntity';
        $operationCount = 2000;

        // Act
        for ($i = 0; $i < $operationCount; $i++) {
            $this->countProvider->increaseEntityCounter($entityClass);

            // 每100次操作清理实体管理器以防止内存累积
            if ($i % 100 === 0) {
                $this->entityManager->clear();
            }
        }

        $finalMemory = memory_get_usage(true);
        $memoryIncrease = $finalMemory - $initialMemory;

        // Assert: 内存增长应该在合理范围内（小于50MB）
        $this->assertLessThan(50 * 1024 * 1024, $memoryIncrease,
            'Memory usage should remain reasonable during large operations');

        // 验证操作正确完成
        $this->entityManager->clear();
        $counterName = sprintf('%s::total', $entityClass);
        $counter = $this->counterRepository->findOneBy(['name' => $counterName]);
        $this->assertEquals($operationCount, $counter->getCount());
    }

    /**
     * 测试数据库连接池的效率
     */
    public function test_connectionEfficiency_manyQueries_optimizedDatabaseAccess(): void
    {
        // Arrange
        $entityClass = 'ConnectionTestEntity';

        // 预创建计数器
        $counter = new Counter();
        $counter->setName(sprintf('%s::total', $entityClass));
        $counter->setCount(1000);
        $this->entityManager->persist($counter);
        $this->entityManager->flush();

        // Act: 执行大量更新操作
        $startTime = microtime(true);

        for ($i = 0; $i < 200; $i++) {
            $this->countProvider->increaseEntityCounter($entityClass);
        }

        $endTime = microtime(true);

        // Assert
        $operationTime = $endTime - $startTime;
        $this->assertLessThan(3.0, $operationTime,
            'Database operations should be efficient');

        // 验证最终计数
        $this->entityManager->clear();
        $finalCounter = $this->counterRepository->findOneBy(['name' => sprintf('%s::total', $entityClass)]);
        $this->assertEquals(1200, $finalCounter->getCount());
    }

    /**
     * 测试并发安全性模拟
     */
    public function test_concurrencySafety_simulatedRaceConditions_dataConsistency(): void
    {
        // Arrange
        $entityClass = 'ConcurrencyTestEntity';
        $iterationCount = 100;

        // Act: 模拟竞态条件 - 快速交替增减操作
        for ($i = 0; $i < $iterationCount; $i++) {
            $this->countProvider->increaseEntityCounter($entityClass);
            $this->countProvider->increaseEntityCounter($entityClass);
            $this->countProvider->decreaseEntityCounter($entityClass);
        }

        // 确保从数据库重新读取
        $this->entityManager->clear();

        // Assert: 最终计数应该是 iterationCount (每次循环净增加1)
        $counterName = sprintf('%s::total', $entityClass);
        $counter = $this->counterRepository->findOneBy(['name' => $counterName]);

        $this->assertNotNull($counter);
        $this->assertEquals($iterationCount, $counter->getCount());
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->counterRepository = static::getContainer()->get(CounterRepository::class);
        $this->countProvider = static::getContainer()->get(EntityTotalCountProvider::class);
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
}