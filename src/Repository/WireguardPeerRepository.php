<?php

namespace App\Repository;

use App\Entity\WireguardPeer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WireguardPeer>
 */
class WireguardPeerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WireguardPeer::class);
    }

    public function findOneByPeerId(int $value): ?WireguardPeer {
        return $this->createQueryBuilder('w')
           ->andWhere('w.peerID = :val')
           ->setParameter('val', $value)
           ->getQuery()
           ->getOneOrNullResult();
    }

    //    /**
    //     * @return WireguardPeer[] Returns an array of WireguardPeer objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('w')
    //            ->andWhere('w.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('w.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?WireguardPeer
    //    {
    //        return $this->createQueryBuilder('w')
    //            ->andWhere('w.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
