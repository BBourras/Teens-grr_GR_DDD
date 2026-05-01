<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Entity\Comment;
use App\Domain\Entity\ModerationActionLog;
use App\Domain\Entity\Post;
use App\Domain\Entity\User;
use App\Domain\Enum\ModerationActionType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository du journal de modération.
 *
 * Fournit les requêtes nécessaires à :
 * - L'historique d'un post ou commentaire spécifique
 * - Le journal d'activité d'un modérateur
 * - Le flux global d'actions (dashboard admin)
 * - La distinction actions automatiques / manuelles
 *
 * Les logs sont immuables : aucune méthode d'écriture ici.
 */
class ModerationActionLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ModerationActionLog::class);
    }

    // ======================================================
    // HISTORIQUE PAR ENTITÉ
    // ======================================================

    public function findModActByPost(Post $post): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.moderator', 'm')
            ->addSelect('m')
            ->where('l.post = :post')
            ->setParameter('post', $post)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findModActByComment(Comment $comment): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.moderator', 'm')
            ->addSelect('m')
            ->where('l.comment = :comment')
            ->setParameter('comment', $comment)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // ======================================================
    // JOURNAL D'ACTIVITÉ MODÉRATEUR
    // ======================================================

    public function findModActByModerator(User $moderator, int $limit = 50): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.moderator = :moderator')
            ->setParameter('moderator', $moderator)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    // ======================================================
    // FLUX GLOBAL (dashboard admin)
    // ======================================================

    public function findRecent(int $limit = 100): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.moderator', 'm')
            ->addSelect('m')
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findAutomatic(int $limit = 50): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.moderator IS NULL')
            ->andWhere('l.actionType = :type')
            ->setParameter('type', ModerationActionType::AUTO_HIDE)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    // ======================================================
    // FILTRAGE PAR TYPE ET PÉRIODE
    // ======================================================

    public function findByTypeAndPeriod(
        ModerationActionType $type,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.moderator', 'm')
            ->addSelect('m')
            ->where('l.actionType = :type')
            ->andWhere('l.createdAt >= :from')
            ->andWhere('l.createdAt <= :to')
            ->setParameter('type', $type)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByTypeAndPeriod(
        ModerationActionType $type,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): int {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.actionType = :type')
            ->andWhere('l.createdAt >= :from')
            ->andWhere('l.createdAt <= :to')
            ->setParameter('type', $type)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findModActByUser(User $user, int $limit = 50): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.moderator', 'm')
            ->addSelect('m')
            ->leftJoin('l.post', 'p')
            ->leftJoin('l.comment', 'c')
            ->where('p.author = :user OR c.author = :user')
            ->setParameter('user', $user)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}