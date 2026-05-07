<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Contract\ModeratableContentInterface;
use App\Domain\Contract\ReportRepositoryInterface;
use App\Domain\Entity\Comment;
use App\Domain\Entity\Post;
use App\Domain\Entity\Report;
use App\Domain\Entity\User;
use App\Domain\Enum\ReportReason;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * ReportRepository – Gestion des signalements.
 *
 * Responsabilités :
 * - Vérification des doublons
 * - Récupération des signalements pour modération
 * - Comptage pour l’auto-modération
 */
class ReportRepository extends ServiceEntityRepository implements ReportRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Report::class);
    }

    // ======================================================
    // ANTI-DOUBLONS
    // ======================================================

    public function hasAlreadyReportedPost(Post $post, User $user): bool
    {
        return $this->countByContent($post, $user) > 0;
    }

    public function hasAlreadyReportedComment(Comment $comment, User $user): bool
    {
        return $this->countByContent($comment, $user) > 0;
    }

    // ======================================================
    // DASHBOARD MODÉRATION
    // ======================================================

    public function findAllByPost(Post $post): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.user', 'u')
            ->addSelect('u')
            ->where('r.post = :post')
            ->orderBy('r.createdAt', 'DESC')
            ->setParameter('post', $post)
            ->getQuery()
            ->getResult();
    }

    public function findAllByComment(Comment $comment): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.user', 'u')
            ->addSelect('u')
            ->where('r.comment = :comment')
            ->orderBy('r.createdAt', 'DESC')
            ->setParameter('comment', $comment)
            ->getQuery()
            ->getResult();
    }

    public function findPendingReports(int $limit = 50): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.user', 'u')
            ->leftJoin('r.post', 'p')
            ->leftJoin('r.comment', 'c')
            ->addSelect('u', 'p', 'c')
            ->where('r.reason IN (:seriousReasons)')
            ->orWhere('r.createdAt >= :recent')
            ->setParameter('seriousReasons', [
                ReportReason::HARASSMENT->value,
                ReportReason::HATE_SPEECH->value,
                ReportReason::INAPPROPRIATE->value,
            ])
            ->setParameter('recent', new \DateTimeImmutable('-7 days'))
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    // ======================================================
    // COMPTAGE
    // ======================================================

    public function countByContent(ModeratableContentInterface $content, ?User $user = null): int
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)');

        if ($content instanceof Post) {
            $qb->where('r.post = :content');
        } else {
            $qb->where('r.comment = :content');
        }

        if ($user !== null) {
            $qb->andWhere('r.user = :user')
               ->setParameter('user', $user);
        }

        return (int) $qb->setParameter('content', $content)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findRecent(int $limit = 20): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.user', 'u')
            ->leftJoin('r.post', 'p')
            ->leftJoin('r.comment', 'c')
            ->addSelect('u', 'p', 'c')
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}