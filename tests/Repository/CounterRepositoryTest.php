<?php

namespace CounterBundle\Tests\Repository;

use CounterBundle\Entity\Counter;
use CounterBundle\Provider\EntityTotalCountProvider;
use CounterBundle\Repository\CounterRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(CounterRepository::class)]
#[RunTestsInSeparateProcesses]
final class CounterRepositoryTest extends AbstractRepositoryTestCase
{
    private EntityTotalCountProvider $countProvider;

    protected function onSetUp(): void
    {
        /** @var EntityTotalCountProvider $countProvider */
        $countProvider = self::getContainer()->get(EntityTotalCountProvider::class);
        $this->countProvider = $countProvider;
    }

    public function testRepositoryServiceIsAvailable(): void
    {
        // 测试仓库服务可以从容器中获取
        $repository = self::getService(CounterRepository::class);

        $this->assertInstanceOf(CounterRepository::class, $repository);
        $this->assertInstanceOf(ServiceEntityRepository::class, $repository);
    }

    /**
     * 测试极长实体类名的处理
     */
    public function testVeryLongEntityClassNameNormalOperationHandlesGracefully(): void
    {
        // Arrange: 创建一个极长的实体类名
        $longEntityClass = str_repeat('VeryLongEntityClassName', 10); // 约200字符

        // Act
        $this->countProvider->increaseEntityCounter($longEntityClass);

        // Assert
        $counterName = sprintf('%s::total', $longEntityClass);
        $counter = $this->getRepository()->findOneBy(['name' => $counterName]);

        $this->assertNotNull($counter);
        $this->assertEquals(1, $counter->getCount());
        $this->assertEquals($counterName, $counter->getName());
    }

    /**
     * 测试包含特殊字符的实体类名
     */
    public function testSpecialCharacterEntityNameNormalOperationHandlesCorrectly(): void
    {
        // Arrange: 包含特殊字符的实体类名
        $specialEntityClass = 'Test\Entity\With\Backslashes';

        // Act
        $this->countProvider->increaseEntityCounter($specialEntityClass);

        // Assert
        $counterName = sprintf('%s::total', $specialEntityClass);
        $counter = $this->getRepository()->findOneBy(['name' => $counterName]);

        $this->assertNotNull($counter);
        $this->assertEquals(1, $counter->getCount());
    }

    /**
     * 测试计数器达到整型最大值的边界情况
     */
    public function testCounterMaxValueNearIntegerLimitHandlesOverflow(): void
    {
        // Arrange: 创建一个接近整型最大值的计数器
        $entityClass = 'MaxValueTestEntity';
        $counterName = sprintf('%s::total', $entityClass);

        $counter = new Counter();
        $counter->setName($counterName);
        $counter->setCount(PHP_INT_MAX - 2); // 接近最大值
        self::getEntityManager()->persist($counter);
        self::getEntityManager()->flush();

        // Act: 尝试增加计数
        $this->countProvider->increaseEntityCounter($entityClass);
        $this->countProvider->increaseEntityCounter($entityClass);

        // Assert: 验证没有溢出错误，计数继续正常
        self::getEntityManager()->clear();
        $updatedCounter = $this->getRepository()->findOneBy(['name' => $counterName]);
        $this->assertNotNull($updatedCounter);
        $this->assertEquals(PHP_INT_MAX, $updatedCounter->getCount());
    }

    /**
     * 测试空字符串和null实体类名的处理
     */
    public function testInvalidEntityClassNameEmptyOrNullHandlesGracefully(): void
    {
        // Act & Assert: 空字符串会创建一个名为 '::total' 的计数器
        $this->countProvider->increaseEntityCounter('');

        self::getEntityManager()->clear();
        $emptyCounter = $this->getRepository()->findOneBy(['name' => '::total']);

        // 根据实际实现，空字符串会被处理为有效的实体类名
        $this->assertNotNull($emptyCounter);
        $this->assertEquals(1, $emptyCounter->getCount());
    }

    /**
     * 测试计数器为负数的异常处理
     */
    public function testNegativeCounterValueDecrementProtectionMaintainsNonNegative(): void
    {
        // Arrange: 创建一个计数为1的计数器
        $entityClass = 'NegativeTestEntity';
        $counterName = sprintf('%s::total', $entityClass);

        $counter = new Counter();
        $counter->setName($counterName);
        $counter->setCount(1);
        self::getEntityManager()->persist($counter);
        self::getEntityManager()->flush();

        // Act: 尝试多次减少计数
        for ($i = 0; $i < 5; ++$i) {
            $this->countProvider->decreaseEntityCounter($entityClass);
        }

        // Assert: 计数应该停在1（由于查询条件 count > 1）
        self::getEntityManager()->clear();
        $updatedCounter = $this->getRepository()->findOneBy(['name' => $counterName]);
        $this->assertNotNull($updatedCounter);
        $this->assertEquals(1, $updatedCounter->getCount());
    }

    public function testSaveMethod(): void
    {
        // Arrange
        $repository = self::getService(CounterRepository::class);
        $counter = new Counter();
        $counter->setName('test-save-method-' . uniqid());
        $counter->setCount(100);

        // Act
        $repository->save($counter);

        // Assert
        $this->assertGreaterThan(0, $counter->getId());

        $savedCounter = $repository->find($counter->getId());
        $this->assertInstanceOf(Counter::class, $savedCounter);
        $this->assertSame($counter->getName(), $savedCounter->getName());
        $this->assertSame(100, $savedCounter->getCount());

        // Cleanup
        $repository->remove($counter);
    }

    public function testRemoveMethod(): void
    {
        // Arrange
        $repository = self::getService(CounterRepository::class);
        $counter = new Counter();
        $counter->setName('test-remove-method-' . uniqid());
        $counter->setCount(50);

        $repository->save($counter);
        $savedId = $counter->getId();

        // Verify it exists
        $this->assertInstanceOf(Counter::class, $repository->find($savedId));

        // Act
        $repository->remove($counter);

        // Assert
        $result = $repository->find($savedId);
        $this->assertNull($result);
    }

    // IS NULL 查询测试
    public function testFindByCountIsNull(): void
    {
        // Arrange
        $repository = self::getService(CounterRepository::class);

        // Act - 由于count字段不能为null（int类型），这个查询应该返回空数组
        $result = $repository->findBy(['count' => null]);

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // findOneBy额外测试
    public function testFindOneByShouldRespectOrderByClause(): void
    {
        // Arrange
        $repository = self::getService(CounterRepository::class);
        $baseTime = time();
        $counter1 = new Counter();
        $counter1->setName("test-order-one-z-{$baseTime}");
        $counter1->setCount(100);
        $counter2 = new Counter();
        $counter2->setName("test-order-one-a-{$baseTime}");
        $counter2->setCount(100);

        $repository->save($counter1);
        $repository->save($counter2);

        // Act - Find by count with ASC order by name
        $result = $repository->findOneBy(['count' => 100], ['name' => 'ASC']);

        // Assert
        $this->assertInstanceOf(Counter::class, $result);
        // Should return the one with alphabetically first name
        $this->assertStringContainsString('test-order-one-a-', (string) $result->getName());

        // Cleanup
        $repository->remove($counter1);
        $repository->remove($counter2);
    }

    // 健壮性测试
    public function testFindByWithInvalidFieldNameShouldThrowException(): void
    {
        // Arrange
        $repository = self::getService(CounterRepository::class);

        // Act & Assert
        $this->expectException(\Exception::class);
        $repository->findBy(['invalid_field_name' => 'test']);
    }

    public function testFindOneByWithInvalidFieldNameShouldThrowException(): void
    {
        // Arrange
        $repository = self::getService(CounterRepository::class);

        // Act & Assert
        $this->expectException(\Exception::class);
        $repository->findOneBy(['invalid_field_name' => 'test']);
    }

    // 额外IS NULL测试
    public function testFindByContextIsNull(): void
    {
        // Arrange
        $repository = self::getService(CounterRepository::class);
        $counter1 = new Counter();
        $counter1->setName('test-null-context-1-' . uniqid());
        $counter1->setCount(10);
        $counter1->setContext(null);
        $counter2 = new Counter();
        $counter2->setName('test-null-context-2-' . uniqid());
        $counter2->setCount(20);
        $counter2->setContext(['key' => 'value']);

        $repository->save($counter1);
        $repository->save($counter2);

        // Act
        $result = $repository->findBy(['context' => null]);

        // Assert
        $this->assertIsArray($result);

        // Find our test counter in the results
        $foundNullContext = false;
        foreach ($result as $counter) {
            if ($counter->getId() === $counter1->getId()) {
                $foundNullContext = true;
                $this->assertNull($counter->getContext());
                break;
            }
        }
        $this->assertTrue($foundNullContext);

        // Cleanup
        $repository->remove($counter1);
        $repository->remove($counter2);
    }

    public function testFindByWithNullContextShouldFindRecords(): void
    {
        // Arrange
        $repository = self::getService(CounterRepository::class);
        $counter = new Counter();
        $counter->setName('test-find-null-' . uniqid());
        $counter->setCount(30);
        // Explicitly set context to null
        $counter->setContext(null);

        $repository->save($counter);

        // Act
        $result = $repository->findBy(['context' => null]);

        // Assert
        $this->assertIsArray($result);

        // Find our test counter
        $found = false;
        foreach ($result as $foundCounter) {
            if ($foundCounter->getId() === $counter->getId()) {
                $found = true;
                $this->assertNull($foundCounter->getContext());
                break;
            }
        }
        $this->assertTrue($found);

        // Cleanup
        $repository->remove($counter);
    }

    // 更多 findOneBy 测试

    // 更多 findBy IS NULL 测试

    // count IS NULL 测试

    // findOneBy 排序测试

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
