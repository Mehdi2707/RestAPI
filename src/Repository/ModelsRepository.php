<?php

namespace App\Repository;

use App\Entity\Models;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Models>
 *
 * @method Models|null find($id, $lockMode = null, $lockVersion = null)
 * @method Models|null findOneBy(array $criteria, array $orderBy = null)
 * @method Models[]    findAll()
 * @method Models[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ModelsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Models::class);
    }

    /**
     * @return Models[] Returns an array of Models objects with pagination and search
     */
    public function findAllWithPaginationAndSearch($page, $limit, $term): array
    {
        $result = [];

        $qb = $this->createQueryBuilder('m')
            ->where('m.title LIKE :term')
            ->orWhere('m.description LIKE :term')
            ->setParameter('term', '%' . $term . '%')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $paginator = new Paginator($qb);
        $data = $paginator->getQuery()->getResult();
        $pages = ceil($paginator->count() / $limit);

        $result['data'] = $data;
        $result['pages'] = $pages;
        $result['page'] = intval($page);
        $result['limit'] = $limit;

        return $result;
    }

    //    /**
    //     * @return Models[] Returns an array of Models objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('m.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Models
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
