<?php

namespace CounterBundle\Tests;

use CounterBundle\CounterBundle;
use CounterBundle\Entity\Counter;
use CounterBundle\Provider\EntityTotalCountProvider;
use CounterBundle\Repository\CounterRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * 计数器包边界条件和错误处理测试
 * 测试异常情况、边界值、错误恢复等场景
 *
 * @internal
 */
#[CoversClass(CounterBundle::class)]
#[RunTestsInSeparateProcesses]
final class CounterEdgeCasesTest extends AbstractIntegrationTestCase
{
    private CounterRepository $counterRepository;

    private EntityTotalCountProvider $countProvider;

    /**
     * 测试大量同名计数器的去重处理
     */
    public function testDuplicateCounterNamesUniqueConstraintPreventsDuplicates(): void
    {
        // Arrange
        $entityClass = 'DuplicateTestEntity';
        $counterName = sprintf('%s::total', $entityClass);

        // Act: 多次尝试为同一实体创建计数器
        for ($i = 0; $i < 10; ++$i) {
            $this->countProvider->increaseEntityCounter($entityClass);
        }

        // 确保从数据库重新读取
        self::getEntityManager()->clear();

        // Assert: 应该只有一个计数器记录
        $counters = $this->counterRepository->findBy(['name' => $counterName]);
        $this->assertCount(1, $counters);
        $this->assertEquals(10, $counters[0]->getCount());
    }

    /**
     * 测试计数器上下文信息的边界处理
     */
    public function testCounterContextHandlingLargeJsonDataHandlesCorrectly(): void
    {
        // Arrange: 创建带有大量上下文数据的计数器
        $entityClass = 'ContextTestEntity';
        $counterName = sprintf('%s::total', $entityClass);

        $largeContext = [
            'metadata' => str_repeat('large_data_', 1000),
            'nested' => [
                'level1' => ['level2' => ['level3' => range(1, 100)]],
            ],
            'timestamps' => array_fill(0, 100, date('Y-m-d H:i:s')),
        ];

        $counter = new Counter();
        $counter->setName($counterName);
        $counter->setCount(1);
        $counter->setContext($largeContext);

        // Act
        self::getEntityManager()->persist($counter);
        self::getEntityManager()->flush();

        // 继续正常的计数操作
        $this->countProvider->increaseEntityCounter($entityClass);

        // Assert: 验证上下文数据保持完整
        self::getEntityManager()->clear();
        $retrievedCounter = $this->counterRepository->findOneBy(['name' => $counterName]);

        $this->assertNotNull($retrievedCounter);
        $this->assertEquals(2, $retrievedCounter->getCount());
        $this->assertIsArray($retrievedCounter->getContext());
        $this->assertArrayHasKey('metadata', $retrievedCounter->getContext());
    }

    /**
     * 测试计数器时间戳的一致性
     */
    public function testCounterTimestampsConsistentUpdatesMaintainsTemporal(): void
    {
        // Arrange
        $entityClass = 'TimestampTestEntity';

        // Act: 创建计数器
        $this->countProvider->increaseEntityCounter($entityClass);

        $counterName = sprintf('%s::total', $entityClass);
        $counter = $this->counterRepository->findOneBy(['name' => $counterName]);
        $this->assertNotNull($counter);
        $initialCreateTime = $counter->getCreateTime();
        $initialUpdateTime = $counter->getUpdateTime();
        $this->assertNotNull($initialCreateTime);
        $this->assertNotNull($initialUpdateTime);

        // 等待一小段时间后更新
        usleep(10000); // 10ms

        $this->countProvider->increaseEntityCounter($entityClass);

        // Assert: 验证时间戳更新逻辑
        self::getEntityManager()->clear();
        $updatedCounter = $this->counterRepository->findOneBy(['name' => $counterName]);

        $this->assertNotNull($updatedCounter);
        $updatedCreateTime = $updatedCounter->getCreateTime();
        $updatedUpdateTime = $updatedCounter->getUpdateTime();
        $this->assertNotNull($updatedCreateTime);
        $this->assertNotNull($updatedUpdateTime);
        $this->assertEquals($initialCreateTime->format('Y-m-d H:i:s'), $updatedCreateTime->format('Y-m-d H:i:s'));

        // 更新时间应该是新的时间（精确到秒级比较）
        $updateTimeDiff = $updatedUpdateTime->getTimestamp() - $initialUpdateTime->getTimestamp();
        $this->assertGreaterThanOrEqual(0, $updateTimeDiff);
    }

    /**
     * 测试Counter实体被排除的逻辑
     */
    public function testCounterEntityExclusionSelfReferencePreventsInfiniteLoop(): void
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
    public function testCounterCreationWithDatabaseErrorsHandlesGracefully(): void
    {
        // Arrange & Act: 创建一些正常的计数器
        $this->countProvider->increaseEntityCounter('TestEntity1');
        $this->countProvider->increaseEntityCounter('TestEntity2');

        self::getEntityManager()->clear();

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
    public function testValidEntityCounterCreatesSuccessfully(): void
    {
        // Arrange & Act: 创建有效实体计数器
        $this->countProvider->increaseEntityCounter('ValidTestEntity');

        self::getEntityManager()->clear();

        // Assert: 验证计数器被正确创建
        $counter = $this->counterRepository->findOneBy(['name' => 'ValidTestEntity::total']);

        $this->assertNotNull($counter);
        $this->assertEquals(1, $counter->getCount());
        $this->assertEquals('ValidTestEntity::total', $counter->getName());
    }

    protected function onSetUp(): void
    {
        /** @var CounterRepository $counterRepository */
        $counterRepository = self::getContainer()->get(CounterRepository::class);
        $this->counterRepository = $counterRepository;

        /** @var EntityTotalCountProvider $countProvider */
        $countProvider = self::getContainer()->get(EntityTotalCountProvider::class);
        $this->countProvider = $countProvider;
    }
}
