<?php

namespace CounterBundle\Tests\Repository;

use CounterBundle\Entity\Counter;
use CounterBundle\Repository\CounterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class CounterRepositoryTest extends TestCase
{
    public function testConstruct(): void
    {
        $metadata = new ClassMetadata(Counter::class);
        
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')
            ->with(Counter::class)
            ->willReturn($metadata);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')
            ->with(Counter::class)
            ->willReturn($entityManager);

        $repository = new CounterRepository($registry);
        
        // 仅测试构造函数不抛出异常
        $this->assertInstanceOf(CounterRepository::class, $repository);
    }
}