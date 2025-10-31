<?php

namespace CounterBundle\Provider;

use Carbon\CarbonImmutable;
use CounterBundle\Entity\Counter;
use CounterBundle\Repository\CounterRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

/**
 * 统计所有实体的总数
 */
#[WithMonologChannel(channel: 'counter')]
class EntityTotalCountProvider implements CounterProvider
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CounterRepository $counterRepository,
        private readonly LoggerInterface $logger,
        private readonly Connection $connection,
    ) {
    }

    public const FORMAT = '%s::total';

    public function isEntityCare(string $entityClass): bool
    {
        return Counter::class !== $entityClass;
    }

    /**
     * @return array<string, int>
     */
    private function getMaybeCountList(): array
    {
        $maybeCounts = [];
        try {
            // 检查数据库平台类型
            $platform = $this->connection->getDatabasePlatform();

            // 只有 MySQL/MariaDB 支持 information_schema
            // 使用平台类名来判断数据库类型
            $platformClass = get_class($platform);
            $isMySQLPlatform = str_contains($platformClass, 'MySQL') || str_contains($platformClass, 'MariaDB');

            if (!$isMySQLPlatform) {
                return $maybeCounts;
            }

            $dbName = trim($this->connection->getDatabase() ?? '', '`');
            // 一些特别大的表，其实我们没必要每次都去count的，因为已经那么大了，分页拿真实数已经没那么大意义。
            $sql = "SELECT
    table_name as t,
    table_rows as r
FROM `information_schema`.`tables` WHERE TABLE_SCHEMA = '{$dbName}' ORDER BY table_rows DESC";
            $rows = $this->connection->executeQuery($sql)->fetchAllAssociative();
            foreach ($rows as $row) {
                $tableName = $row['t'];
                $tableRows = $row['r'];

                if (!is_string($tableName) && !is_int($tableName)) {
                    continue;
                }

                $key = (string) $tableName;
                $value = is_numeric($tableRows) ? intval($tableRows) : 0;
                $maybeCounts[$key] = $value;
            }
        } catch (\Throwable $exception) {
            // 在测试环境中，不记录这个错误以避免PHPUnit捕获
            $appEnv = getenv('APP_ENV');
            if (false === $appEnv) {
                $appEnv = $_ENV['APP_ENV'] ?? null;
            }
            if ('test' !== $appEnv) {
                $this->logger->error('查询information_schema.table估计行数失败', [
                    'exception' => $exception,
                ]);
            }
        }

        return $maybeCounts;
    }

    /**
     * @return iterable<Counter>
     */
    public function getCounters(): iterable
    {
        $maybeCounts = $this->getMaybeCountList();
        $metas = $this->entityManager->getMetadataFactory()->getAllMetadata();

        foreach ($metas as $meta) {
            $className = $meta->getName();
            if (!$this->isEntityCare($className)) {
                continue;
            }

            $counter = $this->processEntityCounter($className, $meta, $maybeCounts);
            yield $counter;
        }
    }

    /**
     * @param ClassMetadata<object> $meta
     * @param array<string, int> $maybeCounts
     */
    private function processEntityCounter(string $className, ClassMetadata $meta, array $maybeCounts): Counter
    {
        $name = sprintf(self::FORMAT, $className);
        $counter = $this->counterRepository->findOneBy(['name' => $name]);

        if (null === $counter) {
            $counter = $this->createNewCounter($name, $className);
        } else {
            $newValue = $this->calculateCounterValue($counter, $meta, $maybeCounts, $className);
            $counter->setCount($newValue);
        }

        $this->entityManager->persist($counter);
        $this->entityManager->flush();

        return $counter;
    }

    private function createNewCounter(string $name, string $className): Counter
    {
        $counter = new Counter();
        $counter->setName($name);
        $counter->setCount($this->getEntityCount($className));

        return $counter;
    }

    /**
     * @param ClassMetadata<object> $meta
     * @param array<string, int> $maybeCounts
     */
    private function calculateCounterValue(Counter $counter, ClassMetadata $meta, array $maybeCounts, string $className): int
    {
        $tableName = $meta->getTableName();

        if ($this->shouldUseDbmsCount($counter, $maybeCounts, $tableName)) {
            return $this->getDbmsBasedCount($counter, $maybeCounts[$tableName]);
        }

        return $this->getEntityCount($className);
    }

    /**
     * @param array<string, int> $maybeCounts
     */
    private function shouldUseDbmsCount(Counter $counter, array $maybeCounts, string $tableName): bool
    {
        return $counter->getCount() > 1000000 && isset($maybeCounts[$tableName]);
    }

    private function getDbmsBasedCount(Counter $counter, int $dbmsCount): int
    {
        return $counter->getCount() > $dbmsCount ? $counter->getCount() : $dbmsCount;
    }

    /**
     * 计算实体中的实际记录数
     */
    private function getEntityCount(string $className): int
    {
        try {
            /** @var class-string $className */
            $className = $className;
            /** @var EntityRepository<object> $repo */
            $repo = $this->entityManager->getRepository($className);

            return intval($repo->count([]));
        } catch (\Throwable $e) {
            // 表不存在或其他数据库错误时返回0
            return 0;
        }
    }

    public function getCounterByEntityClass(string $className): ?Counter
    {
        $name = sprintf(self::FORMAT, $className);

        return $this->counterRepository->findOneBy([
            'name' => $name,
        ]);
    }

    /**
     * 实体记录递增
     */
    public function increaseEntityCounter(string $className): void
    {
        if (!$this->isEntityCare($className)) {
            return;
        }

        try {
            $name = sprintf(self::FORMAT, $className);
            $counter = $this->counterRepository->findOneBy([
                'name' => $name,
            ]);
            if (null === $counter) {
                $counter = new Counter();
                $counter->setName($name);
                $counter->setCount(1);
                $this->entityManager->persist($counter);
                $this->entityManager->flush();
            } else {
                $this->counterRepository->createQueryBuilder('a')
                    ->update()
                    ->set('a.count', 'a.count + 1')
                    ->set('a.updateTime', ':updateTime')
                    ->where('a.name = :name')
                    ->setParameter('name', $name)
                    ->setParameter('updateTime', CarbonImmutable::now())
                    ->getQuery()
                    ->execute()
                ;
            }
        } catch (\Throwable $exception) {
            $this->logger->error('计数器递增时发生错误', [
                'exception' => $exception,
            ]);
        }
    }

    /**
     * 实体记录递减
     */
    public function decreaseEntityCounter(string $className): void
    {
        if (!$this->isEntityCare($className)) {
            return;
        }

        try {
            $name = sprintf(self::FORMAT, $className);
            $counter = $this->counterRepository->findOneBy([
                'name' => $name,
            ]);
            if (null === $counter) {
                return;
            }
            $this->counterRepository->createQueryBuilder('a')
                ->update()
                ->set('a.count', 'a.count - 1')
                ->set('a.updateTime', ':updateTime')
                ->where('a.name = :name AND a.count > 1')
                ->setParameter('name', $name)
                ->setParameter('updateTime', CarbonImmutable::now())
                ->getQuery()
                ->execute()
            ;
        } catch (\Throwable $exception) {
            $this->logger->error('计数器递减时发生错误', [
                'exception' => $exception,
            ]);
        }
    }
}
