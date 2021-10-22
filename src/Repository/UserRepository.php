<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findByActivationToken($token){
        return $this->createQueryBuilder('u')
            ->join('u.globalPropertyAttributes', 'g' )
            ->andWhere('g.propertyKey = :key')
            ->andWhere('g.propertyValue = :token')
            ->setParameter(':key', "user.token.activation")
            ->setParameter(':token', "[\"".$token."\"]")
            ->getQuery()
            ->getResult()
            ;
    }

    public function findAllUnconfirmed(){
        return $this->createQueryBuilder('u')
            ->join('u.globalPropertyAttributes', 'g' )
            ->andWhere('g.propertyKey = :key')
            ->setParameter(':key', "user.token.activation")
            ->getQuery()
            ->getResult()
            ;
    }

    public function findAllDisabled(){
        return $this->createQueryBuilder('u')
            ->andWhere('u.roles = :roles')
            ->setParameter(':roles', "[\"\"]")
            ->getQuery()
            ->getResult()
            ;
    }

    public function findByResetPasswordToken($token){
        return $this->createQueryBuilder('u')
            ->join('u.globalPropertyAttributes', 'g' )
            ->andWhere('g.propertyKey = :key')
            ->andWhere('g.propertyValue = :token')
            ->setParameter(':key', "user.token.resetPassword")
            ->setParameter(':token', "[\"".$token."\"]")
            ->getQuery()
            ->getResult()
            ;
    }

    public function search($criterias){
        $qb = $this->createQueryBuilder('u');
        foreach($criterias as $key => $value){
            $qb->andWhere('u.'.$key.' LIKE :'.$key)
                ->setParameter($key, '%'.$value.'%');
        }
        return $qb->getQuery()->getResult();
    }

    // /**
    //  * @return User[] Returns an array of User objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
