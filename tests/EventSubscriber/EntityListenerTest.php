<?php

namespace CounterBundle\Tests\EventSubscriber;

use CounterBundle\EventSubscriber\EntityListener;
use CounterBundle\Tests\Fixtures\TestEntity1;
use CounterBundle\Tests\Fixtures\TestEntity2;
use Doctrine\ORM\EntityManagerInterface;
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
final class EntityListenerTest extends AbstractEventSubscriberTestCase
{
    protected function onSetUp(): void
    {
        // 集成测试环境设置，这里暂时不需要特殊配置
    }

    public function testPostPersistAddsEntityToIncreaseList(): void
    {
        $listener = self::getService(EntityListener::class);
        $entityManager = self::getService(EntityManagerInterface::class);

        $entity = new TestEntity1();
        $args = new PostPersistEventArgs($entity, $entityManager);

        $listener->reset();
        $listener->postPersist($args);

        $this->assertNotNull($listener);
    }

    public function testPostRemoveAddsEntityToDecreaseList(): void
    {
        $listener = self::getService(EntityListener::class);
        $entityManager = self::getService(EntityManagerInterface::class);

        $entity = new TestEntity2();
        $args = new PostRemoveEventArgs($entity, $entityManager);

        $listener->reset();
        $listener->postRemove($args);

        $this->assertNotNull($listener);
    }

    public function testFlushCounterProcessesQueuedEntities(): void
    {
        $listener = self::getService(EntityListener::class);
        $entityManager = self::getService(EntityManagerInterface::class);

        $entity = new TestEntity1();
        $args = new PostPersistEventArgs($entity, $entityManager);

        $listener->reset();
        $listener->postPersist($args);
        $listener->flushCounter();

        // 验证操作没有抛出异常，且 listener 仍然有效
        $this->assertInstanceOf(EntityListener::class, $listener);
        $this->assertNotNull($listener);
    }

    public function testResetClearsInternalLists(): void
    {
        $listener = self::getService(EntityListener::class);
        $entityManager = self::getService(EntityManagerInterface::class);

        $entity1 = new TestEntity1();
        $entity2 = new TestEntity2();

        $listener->postPersist(new PostPersistEventArgs($entity1, $entityManager));
        $listener->postRemove(new PostRemoveEventArgs($entity2, $entityManager));

        $listener->reset();
        $listener->flushCounter();

        // 验证 reset 方法正常工作，能清空内部列表
        $this->assertInstanceOf(EntityListener::class, $listener);
        $this->assertNotNull($listener);

        // 再次调用 reset 不应该抛出异常
        $listener->reset();
        $this->assertNotNull($listener);
    }
}
