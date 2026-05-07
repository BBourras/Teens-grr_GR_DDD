<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Entity\Comment;
use App\Domain\Entity\ModerationActionLog;
use App\Domain\Entity\Post;
use App\Domain\Entity\User;
use App\Domain\Enum\ModerationActionType;
use App\Domain\Contract\ModeratableContentInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * ModerationActionLogRepository – Journal d'audit des actions de modération.
 *
 * Ce repository est **immuable** (lecture seule).
 * Il fournit l'historique complet pour :
 * - Traçabilité en cas de litige
 * - Dashboard administrateur / modérateur
 * - Statistiques d'activité
 */
class ModerationActionLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ModerationActionLog::class);
    }

    // ======================================================
    // HISTORIQUE PAR CONTENU (Post ou Comment)
    // ======================================================

    /**
     * Historique complet des actions sur un Post.
     */
    public function findByPost(Post $post): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.moderator', 'm')
            ->addSelect('m')
            ->where('l.post = :post')
            ->orderBy('l.createdAt', 'DESC')
            ->setParameter('post', $post)
            ->getQuery()
            ->getResult();
    }

    /**
     * Historique complet des actions sur un Commentaire.
     */
    public function findByComment(Comment $comment): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.moderator', 'm')
            ->addSelect('m')
            ->where('l.comment = :comment')
            ->orderBy('l.createdAt', 'DESC')
            ->setParameter('comment', $comment)
            ->getQuery()
            ->getResult();
    }

    /**
     * Historique générique (Post ou Comment) via l'interface.
     */
    public function findByContent(ModeratableContentInterface $content): array
    {
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.moderator', 'm')
            ->addSelect('m');

        if ($content instanceof Post) {
            $qb->where('l.post = :content');
        } else {
            $qb->where('l.comment = :content');
        }

        return $qb->orderBy('l.createdAt', 'DESC')
            ->setParameter('content', $content)
            ->getQuery()
            ->getResult();
    }

    // ======================================================
    // ACTIVITÉ DES MODÉRATEURS
    // ======================================================

    /**
     * Actions effectuées par un modérateur spécifique.
     */
    public function findByModerator(User $moderator, int $limit = 50): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.post', 'p')
            ->leftJoin('l.comment', 'c')
            ->addSelect('p', 'c')
            ->where('l.moderator = :moderator')
            ->setParameter('moderator', $moderator)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    // ======================================================
    // FLUX GLOBAL & STATISTIQUES
    // ======================================================

    /**
     * Dernières actions de modération (flux général).
     */
    public function findRecent(int $limit = 100): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.moderator', 'm')
            ->leftJoin('l.post', 'p')
            ->leftJoin('l.comment', 'c')
            ->addSelect('m', 'p', 'c')
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Actions automatiques uniquement (masquage auto).
     */
    public function findAutomaticActions(int $limit = 50): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.moderator IS NULL')
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Actions par type et période (utile pour statistiques).
     */
    public function findByTypeAndPeriod(
        ModerationActionType $type,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to
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
        \DateTimeImmutable $to
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
}