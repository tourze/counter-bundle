<?php

namespace CounterBundle\Tests\Integration\EventSubscriber;

use CounterBundle\Entity\Counter;
use CounterBundle\EventSubscriber\EntityListener;
use CounterBundle\Provider\EntityTotalCountProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * EntityListener 的集成测试
 */
class EntityListenerTest extends TestCase
{
    /**
     * @var MockObject&EntityTotalCountProvider
     */
    private $countProvider;

    /**
     * @var MockObject&LoggerInterface
     */
    private $logger;

    /**
     * @var EntityListener
     */
    private $listener;

    /**
     * @var MockObject&EntityManagerInterface
     */
    private $entityManager;

    protected function setUp(): void
    {
        $this->countProvider = $this->createMock(EntityTotalCountProvider::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->listener = new EntityListener(
            $this->countProvider,
            $this->logger
        );
    }

    /**
     * 测试 postPersist 事件处理
     */
    public function testPostPersist(): void
    {
        $entity = new \stdClass();
        $args = new PostPersistEventArgs($entity, $this->entityManager);

        // 调用 postPersist
        $this->listener->postPersist($args);

        // 验证实体被添加到增加列表
        // 然后调用 flushCounter 来验证计数器增加
        $this->countProvider
            ->expects($this->once())
            ->method('increaseEntityCounter')
            ->with('stdClass');

        $this->listener->flushCounter();
    }

    /**
     * 测试 postRemove 事件处理
     */
    public function testPostRemove(): void
    {
        $entity = new \stdClass();
        $args = new PostRemoveEventArgs($entity, $this->entityManager);

        // 调用 postRemove
        $this->listener->postRemove($args);

        // 验证实体被添加到减少列表
        // 然后调用 flushCounter 来验证计数器减少
        $this->countProvider
            ->expects($this->once())
            ->method('decreaseEntityCounter')
            ->with('stdClass');

        $this->listener->flushCounter();
    }

    /**
     * 测试 flushCounter 异常处理
     */
    public function testFlushCounterWithException(): void
    {
        $entity = new \stdClass();
        $args = new PostPersistEventArgs($entity, $this->entityManager);

        // 添加一个实体到增加列表
        $this->listener->postPersist($args);

        // 模拟抛出异常
        $exception = new \Exception('测试异常');
        
        $this->countProvider
            ->expects($this->once())
            ->method('increaseEntityCounter')
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                '更新请求中所有计数器失败',
                $this->callback(function ($context) use ($exception) {
                    return isset($context['exception']) && $context['exception'] === $exception;
                })
            );

        $this->listener->flushCounter();
    }

    /**
     * 测试 reset 方法
     */
    public function testReset(): void
    {
        $entity1 = new \stdClass();
        $entity2 = new Counter();

        // 添加一些实体到列表
        $this->listener->postPersist(new PostPersistEventArgs($entity1, $this->entityManager));
        $this->listener->postRemove(new PostRemoveEventArgs($entity2, $this->entityManager));

        // 重置
        $this->listener->reset();

        // 验证列表被清空 - flushCounter 不应该调用任何方法
        $this->countProvider
            ->expects($this->never())
            ->method('increaseEntityCounter');

        $this->countProvider
            ->expects($this->never())
            ->method('decreaseEntityCounter');

        $this->listener->flushCounter();
    }
}