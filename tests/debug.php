<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use CounterBundle\CounterBundle;
use CounterBundle\Provider\EntityTotalCountProvider;
use CounterBundle\Repository\CounterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Tourze\IntegrationTestKernel\IntegrationTestKernel;

class DebugTest extends KernelTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new IntegrationTestKernel('test', true, [
            CounterBundle::class => ['all' => true],
        ]);
    }

    public function test(): void
    {
        self::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $counterRepository = static::getContainer()->get(CounterRepository::class);
        $countProvider = static::getContainer()->get(EntityTotalCountProvider::class);

        // 清理数据库
        $connection = $entityManager->getConnection();
        $connection->executeStatement('DELETE FROM table_count');

        $entityClass = 'TestEntity';
        $counterName = sprintf('%s::total', $entityClass);

        echo "开始测试计数器逻辑...\n";

        // 第一次调用
        echo "\n=== 第一次调用 ===\n";
        $countProvider->increaseEntityCounter($entityClass);
        
        $counter = $counterRepository->findOneBy(['name' => $counterName]);
        echo "第一次后计数: " . ($counter ? $counter->getCount() : 'null') . "\n";
        echo "第一次后ID: " . ($counter ? $counter->getId() : 'null') . "\n";

        // 第二次调用前清理实体管理器
        $entityManager->clear();
        
        echo "\n=== 第二次调用 ===\n";
        $countProvider->increaseEntityCounter($entityClass);
        
        $entityManager->clear(); 
        $counter = $counterRepository->findOneBy(['name' => $counterName]);
        echo "第二次后计数: " . ($counter ? $counter->getCount() : 'null') . "\n";
        echo "第二次后ID: " . ($counter ? $counter->getId() : 'null') . "\n";

        // 第三次调用
        echo "\n=== 第三次调用 ===\n";
        $countProvider->increaseEntityCounter($entityClass);
        
        $entityManager->clear();
        $counter = $counterRepository->findOneBy(['name' => $counterName]);
        echo "第三次后计数: " . ($counter ? $counter->getCount() : 'null') . "\n";
        echo "第三次后ID: " . ($counter ? $counter->getId() : 'null') . "\n";

        // 验证数据库中的原始数据
        echo "\n=== 直接查询数据库 ===\n";
        $sql = "SELECT id, name, count, create_time FROM table_count WHERE name = ?";
        $stmt = $connection->prepare($sql);
        $result = $stmt->executeQuery([$counterName]);
        $row = $result->fetchAssociative();
        
        if ($row) {
            echo "数据库中的记录: ID={$row['id']}, name={$row['name']}, count={$row['count']}\n";
        } else {
            echo "数据库中没有找到记录\n";
        }

        self::ensureKernelShutdown();
    }
}

$test = new DebugTest('test');
$test->test();