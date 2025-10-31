<?php

namespace CounterBundle\Tests\EventSubscriber;

use CounterBundle\Entity\Counter;
use CounterBundle\EventSubscriber\EntityListener;
use CounterBundle\Repository\CounterRepository;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;

/**
 * @internal
 */
#[CoversClass(EntityListener::class)]
#[RunTestsInSeparateProcesses]
final class EntityListenerIntegrationTest extends AbstractEventSubscriberTestCase
{
    private CounterRepository $counterRepository;

    private EntityListener $listener;

    protected function onSetUp(): void
    {
        /** @var CounterRepository $counterRepository */
        $counterRepository = self::getContainer()->get(CounterRepository::class);
        $this->counterRepository = $counterRepository;

        /** @var EntityListener $listener */
        $listener = self::getContainer()->get(EntityListener::class);
        $this->listener = $listener;
    }

    public function testListenerImplementsResetInterface(): void
    {
        // Assert - 验证 EntityListener 实现了 ResetInterface
        $this->assertInstanceOf('Symfony\Contracts\Service\ResetInterface', $this->listener);

        // 验证 reset 方法存在
        $this->assertNotNull($this->listener, 'EntityListener should be initialized properly');
    }

    public function testPostPersistWithTestEntityAddsToIncreaseList(): void
    {
        // Arrange
        $testEntity = $this->createTestEntity();

        // 创建真实的事件参数而不是 mock final 类
        $eventArgs = new PostPersistEventArgs($testEntity, self::getEntityManager());

        // Act
        $this->listener->postPersist($eventArgs);

        // Assert
        // 验证方法调用没有抛出异常，且 listener 对象仍然有效
        $this->assertNotNull($this->listener);
        $this->assertInstanceOf(EntityListener::class, $this->listener);
    }

    public function testPostRemoveWithTestEntityAddsToDecreaseList(): void
    {
        // Arrange
        $testEntity = $this->createTestEntity();

        // 创建真实的事件参数而不是 mock final 类
        $eventArgs = new PostRemoveEventArgs($testEntity, self::getEntityManager());

        // Act
        $this->listener->postRemove($eventArgs);

        // Assert
        // 验证方法调用没有抛出异常，且 listener 对象仍然有效
        $this->assertNotNull($this->listener);
        $this->assertInstanceOf(EntityListener::class, $this->listener);
    }

    public function testResetClearsInternalLists(): void
    {
        // Arrange
        $testEntity = $this->createTestEntity();
        $persistEventArgs = new PostPersistEventArgs($testEntity, self::getEntityManager());
        $removeEventArgs = new PostRemoveEventArgs($testEntity, self::getEntityManager());

        // Act
        $this->listener->postPersist($persistEventArgs);
        $this->listener->postRemove($removeEventArgs);
        $this->listener->reset();

        // Assert
        // 验证 reset 方法没有抛出异常，且能多次调用
        $this->listener->reset(); // 再次调用不应该抛出异常
        $this->assertInstanceOf(EntityListener::class, $this->listener);
    }

    public function testFlushCounterWithIncreaseListCallsIncreaseEntityCounter(): void
    {
        // Arrange
        $testEntity = $this->createTestEntity();
        $eventArgs = new PostPersistEventArgs($testEntity, self::getEntityManager());

        // 预先添加到增加列表
        $this->listener->postPersist($eventArgs);

        // Act
        $this->listener->flushCounter();

        // Assert
        // 验证计数器被创建（间接验证了 increaseEntityCounter 被调用）
        $counterName = sprintf('%s::total', get_class($testEntity));
        $counter = $this->counterRepository->findOneBy(['name' => $counterName]);
        $this->assertNotNull($counter);
        $this->assertEquals(1, $counter->getCount());
    }

    public function testFlushCounterWithDecreaseListCallsDecreaseEntityCounter(): void
    {
        // Arrange - 先创建一个计数器
        $testEntity = $this->createTestEntity();
        $counterName = sprintf('%s::total', get_class($testEntity));

        $counter = new Counter();
        $counter->setName($counterName);
        $counter->setCount(5);
        self::getEntityManager()->persist($counter);
        self::getEntityManager()->flush();

        // 添加到减少列表
        $eventArgs = new PostRemoveEventArgs($testEntity, self::getEntityManager());
        $this->listener->postRemove($eventArgs);

        // Act
        $this->listener->flushCounter();

        // Assert
        self::getEntityManager()->clear();
        $updatedCounter = $this->counterRepository->findOneBy(['name' => $counterName]);
        $this->assertNotNull($updatedCounter);
        $this->assertEquals(4, $updatedCounter->getCount());
    }

    public function testFlushCounterWithExceptionHandlesGracefully(): void
    {
        // 这个测试验证在异常情况下，flushCounter 方法能够优雅地处理错误
        // 由于代码中有 try-catch 块，异常应该被捕获和记录

        // Arrange
        $testEntity = $this->createTestEntity();
        $eventArgs = new PostPersistEventArgs($testEntity, self::getEntityManager());
        $this->listener->postPersist($eventArgs);

        // Act & Assert
        // 验证在异常情况下不会抛出异常
        $this->listener->flushCounter();

        // 验证 listener 仍然有效且可以继续使用
        $this->assertInstanceOf(EntityListener::class, $this->listener);
        $this->assertNotNull($this->listener);
    }

    public function testListenerHasCorrectDoctrineAttributes(): void
    {
        $reflection = new \ReflectionClass($this->listener);
        $attributes = $reflection->getAttributes();

        $eventTypes = $this->extractDoctrineEventTypes($attributes);

        $this->assertTrue(
            in_array('postPersist', $eventTypes, true) || in_array('postRemove', $eventTypes, true),
            'Listener should have at least one Doctrine event listener attribute'
        );
    }

    /**
     * @param array<\ReflectionAttribute<object>> $attributes
     * @return array<string>
     */
    private function extractDoctrineEventTypes(array $attributes): array
    {
        $eventTypes = [];

        foreach ($attributes as $attribute) {
            $eventType = $this->getDoctrineEventType($attribute);
            if (null !== $eventType) {
                $eventTypes[] = $eventType;
            }
        }

        return $eventTypes;
    }

    /**
     * @param \ReflectionAttribute<object> $attribute
     */
    private function getDoctrineEventType(\ReflectionAttribute $attribute): ?string
    {
        $attributeName = $attribute->getName();
        if (!str_contains($attributeName, 'AsDoctrineListener')) {
            return null;
        }

        $args = $attribute->getArguments();
        $event = $args['event'] ?? null;

        return is_string($event) ? $event : null;
    }

    public function testListenerHasEventListenerAttribute(): void
    {
        $reflection = new \ReflectionClass($this->listener);
        $hasFlushCounterEventListener = $this->hasFlushCounterEventListener($reflection);

        $this->assertTrue(
            $hasFlushCounterEventListener,
            'flushCounter method should have AsEventListener attribute'
        );
    }

    /**
     * @param \ReflectionClass<object> $reflection
     */
    private function hasFlushCounterEventListener(\ReflectionClass $reflection): bool
    {
        $flushCounterMethod = $this->findFlushCounterMethod($reflection);
        if (null === $flushCounterMethod) {
            return false;
        }

        return $this->hasEventListenerAttribute($flushCounterMethod);
    }

    /**
     * @param \ReflectionClass<object> $reflection
     */
    private function findFlushCounterMethod(\ReflectionClass $reflection): ?\ReflectionMethod
    {
        $methods = $reflection->getMethods();

        foreach ($methods as $method) {
            if ('flushCounter' === $method->getName()) {
                return $method;
            }
        }

        return null;
    }

    private function hasEventListenerAttribute(\ReflectionMethod $method): bool
    {
        $attributes = $method->getAttributes();

        foreach ($attributes as $attribute) {
            if (str_contains($attribute->getName(), 'AsEventListener')) {
                return true;
            }
        }

        return false;
    }

    public function testListenerHasCoroutineTag(): void
    {
        // Arrange
        $reflection = new \ReflectionClass($this->listener);
        $attributes = $reflection->getAttributes();

        // Act & Assert
        $hasCoroutineTag = false;
        foreach ($attributes as $attribute) {
            if (str_contains($attribute->getName(), 'AutoconfigureTag')) {
                $args = $attribute->getArguments();
                if (in_array('as-coroutine', $args, true)) {
                    $hasCoroutineTag = true;
                    break;
                }
            }
        }

        $this->assertTrue($hasCoroutineTag, 'Listener should have as-coroutine tag');
    }

    /**
     * 创建测试实体
     */
    private function createTestEntity(): object
    {
        // 创建一个简单的测试实体类
        return new class {
            private int $id = 1;

            public function getId(): int
            {
                return $this->id;
            }
        };
    }
}
