<?php

namespace CounterBundle\Repository;

use CounterBundle\Entity\Counter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Counter|null find($id, $lockMode = null, $lockVersion = null)
 * @method Counter|null findOneBy(array $criteria, array $orderBy = null)
 * @method Counter[] findAll()
 * @method Counter[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CounterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Counter::class);
    }
}
