<?php

namespace CounterBundle\Tests\Provider;

use CounterBundle\Entity\Counter;
use CounterBundle\Provider\EntityTotalCountProvider;
use CounterBundle\Repository\CounterRepository;
use CounterBundle\Tests\Fixtures\TestEntity1;
use CounterBundle\Tests\Fixtures\TestEntity2;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * EntityTotalCountProvider 的单元测试
 */
class EntityTotalCountProviderTest extends TestCase
{
    /**
     * @var MockObject&EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var MockObject&CounterRepository
     */
    private $counterRepository;

    /**
     * @var MockObject&LoggerInterface
     */
    private $logger;

    /**
     * @var MockObject&Connection
     */
    private $connection;

    /**
     * @var EntityTotalCountProvider
     */
    private $provider;

    /**
     * @var MockObject&ClassMetadataFactory
     */
    private $classMetadataFactory;

    /**
     * 测试前的准备工作
     */
    protected function setUp(): void
    {
        // 模拟依赖
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->counterRepository = $this->createMock(CounterRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->connection = $this->createMock(Connection::class);
        $this->classMetadataFactory = $this->createMock(ClassMetadataFactory::class);

        // 创建测试对象
        $this->provider = new EntityTotalCountProvider(
            $this->entityManager,
            $this->counterRepository,
            $this->logger,
            $this->connection
        );
    }

    /**
     * 测试 isEntityCare 方法
     */
    public function testIsEntityCare(): void
    {
        // Counter 实体应该被排除
        $this->assertFalse($this->provider->isEntityCare(Counter::class));

        // 其他实体应该被处理
        $this->assertTrue($this->provider->isEntityCare('App\Entity\SomeEntity'));
    }

    /**
     * 测试增加计数器
     */
    public function testIncreaseEntityCounter(): void
    {
        $entityClass = 'App\Entity\SomeEntity';
        $counterName = sprintf('%s::total', $entityClass);

        // 测试场景1: 计数器不存在，需要创建新计数器
        $this->counterRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['name' => $counterName])
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($counter) use ($counterName) {
                return $counter instanceof Counter
                    && $counter->getName() === $counterName
                    && $counter->getCount() === 1;
            }));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->provider->increaseEntityCounter($entityClass);
    }

    /**
     * 测试减少计数器
     */
    public function testDecreaseEntityCounter(): void
    {
        $entityClass = 'App\Entity\SomeEntity';
        $counterName = sprintf('%s::total', $entityClass);
        $counter = new Counter();
        $counter->setName($counterName);
        $counter->setCount(5);

        // 模拟 QueryBuilder
        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $query = $this->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->getMock();

        // 设置预期的方法调用链
        $this->counterRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['name' => $counterName])
            ->willReturn($counter);

        $this->counterRepository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('a')
            ->willReturn($queryBuilder);

        $queryBuilder->expects($this->once())->method('update')->willReturn($queryBuilder);
        $queryBuilder->expects($this->exactly(2))->method('set')->willReturn($queryBuilder);
        $queryBuilder->expects($this->once())->method('where')->willReturn($queryBuilder);
        $queryBuilder->expects($this->exactly(2))->method('setParameter')->willReturn($queryBuilder);
        $queryBuilder->expects($this->once())->method('getQuery')->willReturn($query);
        $query->expects($this->once())->method('execute');

        $this->provider->decreaseEntityCounter($entityClass);
    }

    /**
     * 测试获取实体计数器
     */
    public function testGetCounterByEntityClass(): void
    {
        $entityClass = 'App\Entity\SomeEntity';
        $counterName = sprintf('%s::total', $entityClass);
        $counter = new Counter();
        $counter->setName($counterName);
        $counter->setCount(10);

        $this->counterRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['name' => $counterName])
            ->willReturn($counter);

        $result = $this->provider->getCounterByEntityClass($entityClass);

        $this->assertSame($counter, $result);
        $this->assertEquals($counterName, $result->getName());
        $this->assertEquals(10, $result->getCount());
    }

    /**
     * 测试获取所有计数器
     */
    public function testGetCounters(): void
    {
        // 模拟数据库元数据
        $metadata1 = new ClassMetadata(TestEntity1::class);
        $metadata1->setTableName('table_entity1');

        $metadata2 = new ClassMetadata(TestEntity2::class);
        $metadata2->setTableName('table_entity2');

        // 设置元数据工厂的行为
        $this->classMetadataFactory
            ->expects($this->once())
            ->method('getAllMetadata')
            ->willReturn([$metadata1, $metadata2]);

        $this->entityManager
            ->expects($this->once())
            ->method('getMetadataFactory')
            ->willReturn($this->classMetadataFactory);

        // 模拟数据库连接获取表行数信息
        $dbResult = $this->createMock(Result::class);
        $dbResult->method('fetchAllAssociative')
            ->willReturn([
                ['t' => 'table_entity1', 'r' => 100],
                ['t' => 'table_entity2', 'r' => 200],
            ]);

        $this->connection->method('getDatabase')->willReturn('test_db');
        $this->connection->method('executeQuery')->willReturn($dbResult);

        // 模拟仓库行为
        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->method('count')->willReturn(123);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($entityRepository);

        // 模拟计数器仓库查询结果
        $this->counterRepository
            ->method('findOneBy')
            ->willReturnCallback(function ($criteria) {
                if ($criteria['name'] === TestEntity1::class . '::total') {
                    return null; // 首个实体没有计数器，需要创建
                } else if ($criteria['name'] === TestEntity2::class . '::total') {
                    $counter = new Counter();
                    $counter->setName(TestEntity2::class . '::total');
                    $counter->setCount(150);
                    return $counter;
                }
                return null;
            });

        // 执行测试
        $counters = iterator_to_array($this->provider->getCounters());

        // 验证结果
        $this->assertCount(2, $counters);

        $this->assertEquals(TestEntity1::class . '::total', $counters[0]->getName());
        $this->assertEquals(123, $counters[0]->getCount());

        $this->assertEquals(TestEntity2::class . '::total', $counters[1]->getName());
        $this->assertEquals(123, $counters[1]->getCount());
    }
}
