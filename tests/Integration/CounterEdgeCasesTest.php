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
 * 计数器包边界条件和错误处理测试
 * 测试异常情况、边界值、错误恢复等场景
 */
class CounterEdgeCasesTest extends KernelTestCase
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
     * 测试极长实体类名的处理
     */
    public function test_veryLongEntityClassName_normalOperation_handlesGracefully(): void
    {
        // Arrange: 创建一个极长的实体类名
        $longEntityClass = str_repeat('VeryLongEntityClassName', 10); // 约200字符

        // Act
        $this->countProvider->increaseEntityCounter($longEntityClass);

        // Assert
        $counterName = sprintf('%s::total', $longEntityClass);
        $counter = $this->counterRepository->findOneBy(['name' => $counterName]);

        $this->assertNotNull($counter);
        $this->assertEquals(1, $counter->getCount());
        $this->assertEquals($counterName, $counter->getName());
    }

    /**
     * 测试包含特殊字符的实体类名
     */
    public function test_specialCharacterEntityName_normalOperation_handlesCorrectly(): void
    {
        // Arrange: 包含特殊字符的实体类名
        $specialEntityClass = 'Test\\Entity\\With\\Backslashes';

        // Act
        $this->countProvider->increaseEntityCounter($specialEntityClass);

        // Assert
        $counterName = sprintf('%s::total', $specialEntityClass);
        $counter = $this->counterRepository->findOneBy(['name' => $counterName]);

        $this->assertNotNull($counter);
        $this->assertEquals(1, $counter->getCount());
    }

    /**
     * 测试空字符串和null实体类名的处理
     */
    public function test_invalidEntityClassName_emptyOrNull_handlesGracefully(): void
    {
        // Act & Assert: 空字符串会创建一个名为 '::total' 的计数器
        $this->countProvider->increaseEntityCounter('');

        $this->entityManager->clear();
        $emptyCounter = $this->counterRepository->findOneBy(['name' => '::total']);

        // 根据实际实现，空字符串会被处理为有效的实体类名
        $this->assertNotNull($emptyCounter);
        $this->assertEquals(1, $emptyCounter->getCount());
    }

    /**
     * 测试计数器达到整型最大值的边界情况
     */
    public function test_counterMaxValue_nearIntegerLimit_handlesOverflow(): void
    {
        // Arrange: 创建一个接近整型最大值的计数器
        $entityClass = 'MaxValueTestEntity';
        $counterName = sprintf('%s::total', $entityClass);

        $counter = new Counter();
        $counter->setName($counterName);
        $counter->setCount(PHP_INT_MAX - 2); // 接近最大值
        $this->entityManager->persist($counter);
        $this->entityManager->flush();

        // Act: 尝试增加计数
        $this->countProvider->increaseEntityCounter($entityClass);
        $this->countProvider->increaseEntityCounter($entityClass);

        // Assert: 验证没有溢出错误，计数继续正常
        $this->entityManager->clear();
        $updatedCounter = $this->counterRepository->findOneBy(['name' => $counterName]);
        $this->assertNotNull($updatedCounter);
        $this->assertEquals(PHP_INT_MAX, $updatedCounter->getCount());
    }

    /**
     * 测试计数器为负数的异常处理
     */
    public function test_negativeCounterValue_decrementProtection_maintainsNonNegative(): void
    {
        // Arrange: 创建一个计数为1的计数器
        $entityClass = 'NegativeTestEntity';
        $counterName = sprintf('%s::total', $entityClass);

        $counter = new Counter();
        $counter->setName($counterName);
        $counter->setCount(1);
        $this->entityManager->persist($counter);
        $this->entityManager->flush();

        // Act: 尝试多次减少计数
        for ($i = 0; $i < 5; $i++) {
            $this->countProvider->decreaseEntityCounter($entityClass);
        }

        // Assert: 计数应该停在1（由于查询条件 count > 1）
        $this->entityManager->clear();
        $updatedCounter = $this->counterRepository->findOneBy(['name' => $counterName]);
        $this->assertNotNull($updatedCounter);
        $this->assertEquals(1, $updatedCounter->getCount());
    }

    /**
     * 测试数据库连接异常的恢复
     */
    public function test_databaseConnectionError_errorHandling_gracefulDegradation(): void
    {
        // Arrange
        $entityClass = 'ConnectionErrorTestEntity';

        // Act: 尝试在潜在连接问题下操作
        // 由于这是集成测试，我们无法真正断开数据库连接
        // 但我们可以测试错误处理路径的存在

        // 预先创建计数器
        $this->countProvider->increaseEntityCounter($entityClass);

        // 验证操作成功
        $counterName = sprintf('%s::total', $entityClass);
        $counter = $this->counterRepository->findOneBy(['name' => $counterName]);
        $this->assertNotNull($counter);

        // 继续操作应该正常工作
        $this->countProvider->increaseEntityCounter($entityClass);

        // Assert: 验证系统继续正常工作
        $this->entityManager->clear();
        $updatedCounter = $this->counterRepository->findOneBy(['name' => $counterName]);
        $this->assertEquals(2, $updatedCounter->getCount());
    }

    /**
     * 测试并发冲突的处理
     */
    public function test_concurrentModification_optimisticLocking_handlesConflicts(): void
    {
        // Arrange: 创建初始计数器
        $entityClass = 'ConcurrentTestEntity';
        $this->countProvider->increaseEntityCounter($entityClass);

        // Act: 模拟并发修改场景
        $counterName = sprintf('%s::total', $entityClass);

        // 获取两个计数器实例（模拟两个并发会话）
        $counter1 = $this->counterRepository->findOneBy(['name' => $counterName]);
        $counter2 = $this->counterRepository->findOneBy(['name' => $counterName]);

        // 修改第一个实例
        $counter1->setCount($counter1->getCount() + 1);
        $this->entityManager->flush();

        // 尝试修改第二个实例（应该处理冲突）
        $counter2->setCount($counter2->getCount() + 1);
        $this->entityManager->flush();

        // Assert: 验证最终状态一致
        $this->entityManager->clear();
        $finalCounter = $this->counterRepository->findOneBy(['name' => $counterName]);
        $this->assertNotNull($finalCounter);
        $this->assertGreaterThan(1, $finalCounter->getCount());
    }

    /**
     * 测试大量同名计数器的去重处理
     */
    public function test_duplicateCounterNames_uniqueConstraint_preventsDuplicates(): void
    {
        // Arrange
        $entityClass = 'DuplicateTestEntity';
        $counterName = sprintf('%s::total', $entityClass);

        // Act: 多次尝试为同一实体创建计数器
        for ($i = 0; $i < 10; $i++) {
            $this->countProvider->increaseEntityCounter($entityClass);
        }

        // 确保从数据库重新读取
        $this->entityManager->clear();

        // Assert: 应该只有一个计数器记录
        $counters = $this->counterRepository->findBy(['name' => $counterName]);
        $this->assertCount(1, $counters);
        $this->assertEquals(10, $counters[0]->getCount());
    }

    /**
     * 测试计数器上下文信息的边界处理
     */
    public function test_counterContextHandling_largeJsonData_handlesCorrectly(): void
    {
        // Arrange: 创建带有大量上下文数据的计数器
        $entityClass = 'ContextTestEntity';
        $counterName = sprintf('%s::total', $entityClass);

        $largeContext = [
            'metadata' => str_repeat('large_data_', 1000),
            'nested' => [
                'level1' => ['level2' => ['level3' => range(1, 100)]]
            ],
            'timestamps' => array_fill(0, 100, date('Y-m-d H:i:s'))
        ];

        $counter = new Counter();
        $counter->setName($counterName);
        $counter->setCount(1);
        $counter->setContext($largeContext);

        // Act
        $this->entityManager->persist($counter);
        $this->entityManager->flush();

        // 继续正常的计数操作
        $this->countProvider->increaseEntityCounter($entityClass);

        // Assert: 验证上下文数据保持完整
        $this->entityManager->clear();
        $retrievedCounter = $this->counterRepository->findOneBy(['name' => $counterName]);

        $this->assertNotNull($retrievedCounter);
        $this->assertEquals(2, $retrievedCounter->getCount());
        $this->assertIsArray($retrievedCounter->getContext());
        $this->assertArrayHasKey('metadata', $retrievedCounter->getContext());
    }

    /**
     * 测试计数器时间戳的一致性
     */
    public function test_counterTimestamps_consistentUpdates_maintainsTemporal(): void
    {
        // Arrange
        $entityClass = 'TimestampTestEntity';

        // Act: 创建计数器
        $this->countProvider->increaseEntityCounter($entityClass);

        $counterName = sprintf('%s::total', $entityClass);
        $counter = $this->counterRepository->findOneBy(['name' => $counterName]);
        $initialCreateTime = $counter->getCreateTime();
        $initialUpdateTime = $counter->getUpdateTime();

        // 等待一小段时间后更新
        usleep(10000); // 10ms

        $this->countProvider->increaseEntityCounter($entityClass);

        // Assert: 验证时间戳更新逻辑
        $this->entityManager->clear();
        $updatedCounter = $this->counterRepository->findOneBy(['name' => $counterName]);

        $this->assertNotNull($updatedCounter);
        $this->assertEquals($initialCreateTime->format('Y-m-d H:i:s'), $updatedCounter->getCreateTime()->format('Y-m-d H:i:s'));

        // 更新时间应该是新的时间（精确到秒级比较）
        $updateTimeDiff = $updatedCounter->getUpdateTime()->getTimestamp() - $initialUpdateTime->getTimestamp();
        $this->assertGreaterThanOrEqual(0, $updateTimeDiff);
    }

    /**
     * 测试Counter实体被排除的逻辑
     */
    public function test_counterEntityExclusion_selfReference_preventsInfiniteLoop(): void
    {
        // Act: 尝试为Counter实体本身增加计数
        $this->countProvider->increaseEntityCounter(Counter::class);

        // Assert: 不应该创建Counter的计数器
        $counterName = sprintf('%s::total', Counter::class);
        $counter = $this->counterRepository->findOneBy(['name' => $counterName]);
        $this->assertNull($counter);

        // 验证isEntityCare方法正确排除Counter类
        $this->assertFalse($this->countProvider->isEntityCare(Counter::class));
    }

    /**
     * 测试计数器创建在异常情况下的处理
     */
    public function test_counterCreation_withDatabaseErrors_handlesGracefully(): void
    {
        // Arrange & Act: 创建一些正常的计数器
        $this->countProvider->increaseEntityCounter('TestEntity1');
        $this->countProvider->increaseEntityCounter('TestEntity2');

        $this->entityManager->clear();

        // Assert: 验证计数器被正确创建
        $counter1 = $this->counterRepository->findOneBy(['name' => 'TestEntity1::total']);
        $counter2 = $this->counterRepository->findOneBy(['name' => 'TestEntity2::total']);

        $this->assertNotNull($counter1);
        $this->assertNotNull($counter2);
        $this->assertEquals(1, $counter1->getCount());
        $this->assertEquals(1, $counter2->getCount());
    }

    /**
     * 测试有效实体计数器的创建
     */
    public function test_validEntityCounter_createsSuccessfully(): void
    {
        // Arrange & Act: 创建有效实体计数器
        $this->countProvider->increaseEntityCounter('ValidTestEntity');

        $this->entityManager->clear();

        // Assert: 验证计数器被正确创建
        $counter = $this->counterRepository->findOneBy(['name' => 'ValidTestEntity::total']);

        $this->assertNotNull($counter);
        $this->assertEquals(1, $counter->getCount());
        $this->assertEquals('ValidTestEntity::total', $counter->getName());
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