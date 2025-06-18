<?php

namespace CounterBundle\Provider;

use Carbon\Carbon;
use CounterBundle\Entity\Counter;
use CounterBundle\Repository\CounterRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * 统计所有实体的总数
 */
class EntityTotalCountProvider implements CounterProvider
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CounterRepository $counterRepository,
        private readonly LoggerInterface $logger,
        private readonly Connection $connection,
    )
    {
    }

    const FORMAT = "%s::total";

    public function isEntityCare(string $entityClass): bool
    {
        return $entityClass !== Counter::class;
    }

    private function getMaybeCountList(): array
    {
        $maybeCounts = [];
        try {
            $dbName = trim($this->connection->getDatabase(), '`');
            // 一些特别大的表，其实我们没必要每次都去count的，因为已经那么大了，分页拿真实数已经没那么大意义。
            $sql = "SELECT 
    table_name as t,
    table_rows as r
FROM `information_schema`.`tables` WHERE TABLE_SCHEMA = '{$dbName}' ORDER BY table_rows DESC";
            $rows = $this->connection->executeQuery($sql)->fetchAllAssociative();
            foreach ($rows as $row) {
                $maybeCounts[$row['t']] = intval($row['r']);
            }
        } catch (\Throwable $exception) {
            $this->logger->error('查询information_schema.table估计行数失败', [
                'exception' => $exception,
            ]);
        }

        return $maybeCounts;
    }

    public function getCounters(): iterable
    {
        $maybeCounts = $this->getMaybeCountList();

        $metas = $this->entityManager->getMetadataFactory()->getAllMetadata();
        foreach ($metas as $meta) {
            $className = $meta->getName();
            if (!$this->isEntityCare($className)) {
                continue;
            }

            $name = sprintf(self::FORMAT, $className);
            $counter = $this->counterRepository->findOneBy([
                'name' => $name,
            ]);

            $newValue = 0;
            if ($counter === null) {
                $counter = new Counter();
                $counter->setName($name);

                // 这里总是会实时同步一次，修正数据
                $newValue = $this->getEntityCount($className);
            } else {
                $tableName = $meta->getTableName();
                // 如果数据已经很大，那我们就直接读dbms统计中的数据即可
                if ($counter->getCount() > 1000000 && isset($maybeCounts[$tableName])) {
                    // 如果当前计数器的数，比dbms的数都要大，那我们信任当前计数器的数
                    // 因为可能是其他地方递增或递减了
                    if ($counter->getCount() > $maybeCounts[$tableName]) {
                        $newValue = $counter->getCount();
                    } else {
                        $newValue = $maybeCounts[$tableName];
                    }
                }

                if ($newValue === 0) {
                    $newValue = $this->getEntityCount($className);
                }
            }

            $counter->setCount($newValue);
            $this->entityManager->persist($counter);
            $this->entityManager->flush();
            yield $counter;
        }
    }

    /**
     * 计算实体中的实际记录数
     */
    private function getEntityCount(string $className): int
    {
        $repo = $this->entityManager->getRepository($className);
        return intval($repo->count([]));
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
            if ($counter === null) {
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
                    ->setParameter('updateTime', Carbon::now())
                    ->getQuery()
                    ->execute();
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
            if ($counter === null) {
                return;
            }
            $this->counterRepository->createQueryBuilder('a')
                ->update()
                ->set('a.count', 'a.count - 1')
                ->set('a.updateTime', ':updateTime')
                ->where('a.name = :name AND a.count > 1')
                ->setParameter('name', $name)
                ->setParameter('updateTime', Carbon::now())
                ->getQuery()
                ->execute();
        } catch (\Throwable $exception) {
            $this->logger->error('计数器递减时发生错误', [
                'exception' => $exception,
            ]);
        }
    }
}
