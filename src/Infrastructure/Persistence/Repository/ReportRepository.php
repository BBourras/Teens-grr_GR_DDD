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
 * Repository des signalements (Reports).
 *
 * Responsabilités :
 * - Vérification de doublons (hasAlreadyReported*)
 * - Récupération des signalements pour le dashboard modération
 * - Comptage pour l’auto-modération
 *
 * Les compteurs dénormalisés (reportCount) sont maintenus par ReportService,
 * pas par ce repository.
 */
class ReportRepository extends ServiceEntityRepository implements ReportRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Report::class);
    }

    public function hasAlreadyReportedPost(Post $post, User $user): bool
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.post = :post')
            ->andWhere('r.user = :user')
            ->setParameter('post', $post)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function hasAlreadyReportedComment(Comment $comment, User $user): bool
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.comment = :comment')
            ->andWhere('r.user = :user')
            ->setParameter('comment', $comment)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

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
            ->orderBy('FIELD(r.reason, :seriousReasons)', 'ASC') // priorise les graves
            ->addOrderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByContent(ModeratableContentInterface $content): int
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)');

        if ($content instanceof Post) {
            $qb->where('r.post = :content');
        } else {
            $qb->where('r.comment = :content');
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