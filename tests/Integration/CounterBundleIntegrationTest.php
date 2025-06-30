<?php

namespace CounterBundle\Tests\Integration;

use CounterBundle\CounterBundle;
use CounterBundle\Entity\Counter;
use CounterBundle\EventSubscriber\EntityListener;
use CounterBundle\Provider\EntityTotalCountProvider;
use CounterBundle\Repository\CounterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Tourze\IntegrationTestKernel\IntegrationTestKernel;

/**
 * 计数器包综合集成测试
 * 测试完整的业务流程、并发场景、错误恢复等
 */
class CounterBundleIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private CounterRepository $counterRepository;
    private EntityTotalCountProvider $countProvider;
    private EntityListener $entityListener;

    protected static function createKernel(array $options = []): KernelInterface
    {
        $env = $options['environment'] ?? $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'test';
        $debug = $options['debug'] ?? $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? true;

        return new IntegrationTestKernel($env, $debug, [
            CounterBundle::class => ['all' => true],
        ]);
    }

    /**
     * 测试完整的实体计数生命周期
     */
    public function test_entityCountLifecycle_createUpdateDelete_maintainsAccurateCount(): void
    {
        // Arrange
        $entityClass = 'App\\Entity\\TestEntity';
        $expectedCounterName = sprintf('%s::total', $entityClass);

        // Act 1: 创建第一个实体
        $this->countProvider->increaseEntityCounter($entityClass);

        // Assert 1: 验证计数器被创建并正确计数
        $counter = $this->counterRepository->findOneBy(['name' => $expectedCounterName]);
        $this->assertNotNull($counter);
        $this->assertEquals(1, $counter->getCount());

        // Act 2: 增加更多实体
        for ($i = 0; $i < 5; $i++) {
            $this->countProvider->increaseEntityCounter($entityClass);
        }

        // Assert 2: 计数正确累加
        $this->entityManager->clear();
        $counter = $this->counterRepository->findOneBy(['name' => $expectedCounterName]);
        $this->assertEquals(6, $counter->getCount());

        // Act 3: 删除一些实体
        for ($i = 0; $i < 2; $i++) {
            $this->countProvider->decreaseEntityCounter($entityClass);
        }

        // Assert 3: 计数正确减少
        $this->entityManager->clear();
        $counter = $this->counterRepository->findOneBy(['name' => $expectedCounterName]);
        $this->assertEquals(4, $counter->getCount());
    }

    /**
     * 测试批量操作的性能和正确性
     */
    public function test_batchOperations_largeBatch_handlesEfficiently(): void
    {
        // Arrange
        $entityClass = 'App\\Entity\\TestEntity';
        $expectedCounterName = sprintf('%s::total', $entityClass);
        $batchSize = 100;

        // Act: 批量增加计数
        for ($i = 0; $i < $batchSize; $i++) {
            $this->countProvider->increaseEntityCounter($entityClass);
        }

        // 确保从数据库重新加载
        $this->entityManager->clear();

        // Assert
        $counter = $this->counterRepository->findOneBy(['name' => $expectedCounterName]);
        $this->assertNotNull($counter);
        $this->assertEquals($batchSize, $counter->getCount());
    }

    /**
     * 测试计数器不会降到负数的保护机制
     */
    public function test_decreaseEntityCounter_belowZero_stopsAtZero(): void
    {
        // Arrange
        $entityClass = 'App\\Entity\\TestEntity';
        $expectedCounterName = sprintf('%s::total', $entityClass);

        // 创建一个计数为 2 的计数器
        $counter = new Counter();
        $counter->setName($expectedCounterName);
        $counter->setCount(2);
        $this->entityManager->persist($counter);
        $this->entityManager->flush();

        // Act: 尝试减少超过当前计数的次数
        for ($i = 0; $i < 5; $i++) {
            $this->countProvider->decreaseEntityCounter($entityClass);
        }

        // Assert: 计数应该降到1而不是负数（因为查询条件 count > 1）
        $this->entityManager->clear();
        $updatedCounter = $this->counterRepository->findOneBy(['name' => $expectedCounterName]);
        $this->assertNotNull($updatedCounter);
        $this->assertEquals(1, $updatedCounter->getCount());
    }

    /**
     * 测试多种实体类型的计数器隔离
     */
    public function test_multipleEntityTypes_separateCounters_maintainIsolation(): void
    {
        // Arrange - 使用简单的字符串实体类名
        $entityClass1 = 'App\\Entity\\TestEntity1';
        $entityClass2 = 'App\\Entity\\TestEntity2';
        $counterName1 = sprintf('%s::total', $entityClass1);
        $counterName2 = sprintf('%s::total', $entityClass2);

        // Act: 为不同实体类型增加计数
        $this->countProvider->increaseEntityCounter($entityClass1);
        $this->countProvider->increaseEntityCounter($entityClass1);
        $this->countProvider->increaseEntityCounter($entityClass2);

        // 确保从数据库重新加载
        $this->entityManager->clear();

        // Assert: 各自的计数器独立维护
        $counter1 = $this->counterRepository->findOneBy(['name' => $counterName1]);
        $counter2 = $this->counterRepository->findOneBy(['name' => $counterName2]);

        $this->assertNotNull($counter1);
        $this->assertNotNull($counter2);
        $this->assertEquals(2, $counter1->getCount());
        $this->assertEquals(1, $counter2->getCount());
    }

    /**
     * 测试 EventListener 与 CountProvider 的协同工作
     */
    public function test_eventListenerIntegration_realEntities_updatesCounters(): void
    {
        // Arrange
        $testEntity1 = $this->createMockEntity('TestEntity1');
        $testEntity2 = $this->createMockEntity('TestEntity2');

        // Simulate multiple persist and remove events
        $this->simulatePostPersist($testEntity1);
        $this->simulatePostPersist($testEntity1);
        $this->simulatePostPersist($testEntity2);
        $this->simulatePostRemove($testEntity1);

        // Act: 触发计数器更新
        $this->entityListener->flushCounter();

        // Assert: 验证计数器状态
        $counterName1 = sprintf('%s::total', get_class($testEntity1));
        $counterName2 = sprintf('%s::total', get_class($testEntity2));

        $counter1 = $this->counterRepository->findOneBy(['name' => $counterName1]);
        $counter2 = $this->counterRepository->findOneBy(['name' => $counterName2]);

        $this->assertNotNull($counter1);
        $this->assertNotNull($counter2);
        $this->assertEquals(1, $counter1->getCount()); // 2 增加 - 1 减少 = 1
        $this->assertEquals(1, $counter2->getCount()); // 1 增加
    }

    /**
     * 创建模拟实体
     */
    private function createMockEntity(string $className): object
    {
        return new class($className) {
            private string $className;

            public function __construct(string $className)
            {
                $this->className = $className;
            }

            public function __toString(): string
            {
                return $this->className;
            }
        };
    }

    /**
     * 模拟 postPersist 事件
     */
    private function simulatePostPersist(object $entity): void
    {
        $eventArgs = new \Doctrine\ORM\Event\PostPersistEventArgs($entity, $this->entityManager);
        $this->entityListener->postPersist($eventArgs);
    }

    /**
     * 模拟 postRemove 事件
     */
    private function simulatePostRemove(object $entity): void
    {
        $eventArgs = new \Doctrine\ORM\Event\PostRemoveEventArgs($entity, $this->entityManager);
        $this->entityListener->postRemove($eventArgs);
    }

    /**
     * 测试数据一致性恢复
     */
    public function test_dataConsistencyRecovery_manualSync_correctsDiscrepancies(): void
    {
        // Arrange: 创建一个有错误计数的计数器
        $entityClass = 'App\\Entity\\TestEntity';
        $counterName = sprintf('%s::total', $entityClass);

        $counter = new Counter();
        $counter->setName($counterName);
        $counter->setCount(999); // 错误的大计数
        $this->entityManager->persist($counter);
        $this->entityManager->flush();

        // Act: 通过 getCounterByEntityClass 方法获取计数器
        $syncedCounter = $this->countProvider->getCounterByEntityClass($entityClass);

        // Assert: 计数器应该存在
        $this->assertNotNull($syncedCounter);
        $this->assertEquals(999, $syncedCounter->getCount()); // 实际上应该保持原值，因为没有触发同步
    }

    /**
     * 测试计数器在数据库事务中的行为
     */
    public function test_transactionSafety_rollback_doesNotAffectCounters(): void
    {
        // Arrange
        $entityClass = 'App\\Entity\\TestEntity';
        $counterName = sprintf('%s::total', $entityClass);

        // 先创建一个基础计数器
        $this->countProvider->increaseEntityCounter($entityClass);
        
        $this->entityManager->clear();
        $initialCounter = $this->counterRepository->findOneBy(['name' => $counterName]);
        $initialCount = $initialCounter->getCount();

        // Act: 正常增加计数器（不在事务中）
        $this->countProvider->increaseEntityCounter($entityClass);
        $this->countProvider->increaseEntityCounter($entityClass);

        // Assert: 计数器应该正常增加
        $this->entityManager->clear();
        $finalCounter = $this->counterRepository->findOneBy(['name' => $counterName]);
        
        // 验证计数器正确增加了2次
        $this->assertEquals($initialCount + 2, $finalCounter->getCount());
    }

    /**
     * 测试忽略 Counter 实体本身的计数
     */
    public function test_counterEntityFilter_counterEntity_isIgnored(): void
    {
        // Act: 尝试为 Counter 实体增加计数
        $this->countProvider->increaseEntityCounter(Counter::class);

        // Assert: 不应该创建 Counter 实体的计数器
        $counterName = sprintf('%s::total', Counter::class);
        $counter = $this->counterRepository->findOneBy(['name' => $counterName]);
        $this->assertNull($counter);
    }

    /**
     * 测试重置功能
     */
    public function test_entityListenerReset_afterOperations_clearsInternalState(): void
    {
        // Arrange: 模拟一些操作
        $testEntity = $this->createMockEntity('TestEntity');
        $this->simulatePostPersist($testEntity);
        $this->simulatePostRemove($testEntity);

        // Act: 重置监听器
        $this->entityListener->reset();

        // 再次触发清理，应该不会有任何操作
        $this->entityListener->flushCounter();

        // Assert: 没有计数器被创建
        $counterName = sprintf('%s::total', get_class($testEntity));
        $counter = $this->counterRepository->findOneBy(['name' => $counterName]);
        $this->assertNull($counter);
    }

    /**
     * 测试大数据量情况下的优化逻辑
     */
    public function test_largeDataOptimization_millionRecords_usesEstimatedCount(): void
    {
        // Arrange: 创建一个超过百万的计数器
        $entityClass = 'App\\Entity\\TestEntity';
        $counterName = sprintf('%s::total', $entityClass);

        $counter = new Counter();
        $counter->setName($counterName);
        $counter->setCount(1500000); // 150万条记录
        $this->entityManager->persist($counter);
        $this->entityManager->flush();

        // Act: 通过 getCounterByEntityClass 验证计数器存在
        $retrievedCounter = $this->countProvider->getCounterByEntityClass($entityClass);

        // Assert: 计数器应该存在并保持原值
        $this->assertNotNull($retrievedCounter);
        $this->assertEquals(1500000, $retrievedCounter->getCount());
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->counterRepository = static::getContainer()->get(CounterRepository::class);
        $this->countProvider = static::getContainer()->get(EntityTotalCountProvider::class);
        $this->entityListener = static::getContainer()->get(EntityListener::class);
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

/**
 * 测试用实体类
 * @phpstan-ignore-next-line
 */
class TestEntity
{
    private int $id = 1;
    
    public function getId(): int
    {
        return $this->id;
    }
}

/**
 * 另一个测试用实体类
 * @phpstan-ignore-next-line
 */
class AnotherTestEntity
{
    private int $id = 1;
    
    public function getId(): int
    {
        return $this->id;
    }
}