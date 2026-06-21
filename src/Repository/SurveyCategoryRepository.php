<?php

namespace App\Repository;

use App\Entity\SurveyCategories;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SurveyCategories>
 */
class SurveyCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SurveyCategories::class);
    }

//    /**
//     * @return SurveyCategories[] Returns an array of SurveyCategories objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('s.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?SurveyCategories
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
