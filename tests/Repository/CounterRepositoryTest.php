<?php

namespace CounterBundle\Tests\Repository;

use CounterBundle\Entity\Counter;
use CounterBundle\Repository\CounterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * CounterRepository 的单元测试
 */
class CounterRepositoryTest extends TestCase
{
    /**
     * @var MockObject&ManagerRegistry
     */
    private $registry;

    /**
     * @var MockObject&EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var CounterRepository
     */
    private $repository;

    /**
     * 测试前的准备工作
     */
    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        // 配置 registry 以返回 entityManager
        $this->registry->expects($this->any())
            ->method('getManagerForClass')
            ->with(Counter::class)
            ->willReturn($this->entityManager);

        $this->repository = new CounterRepository($this->registry);
    }

    /**
     * 测试构造函数注入
     */
    public function testConstructor(): void
    {
        $this->assertInstanceOf(CounterRepository::class, $this->repository);
    }

    /**
     * 测试继承的基础方法
     */
    public function testInheritedMethods(): void
    {
        // 这些方法是从父类继承的，所以我们只需确保它们存在
        $this->assertTrue(method_exists($this->repository, 'find'));
        $this->assertTrue(method_exists($this->repository, 'findAll'));
        $this->assertTrue(method_exists($this->repository, 'findBy'));
        $this->assertTrue(method_exists($this->repository, 'findOneBy'));
        $this->assertTrue(method_exists($this->repository, 'count'));
    }
}
