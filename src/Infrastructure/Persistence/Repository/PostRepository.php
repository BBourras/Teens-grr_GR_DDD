<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Entity\Post;
use App\Domain\Entity\User;
use App\Domain\Enum\ContentStatus;
use App\Domain\Enum\VoteType;
use App\Domain\Contract\PostRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository principal des Posts.
 *
 * Optimisations systématiques :
 * - Tous les classements sont calculés en SQL (pas de tri PHP)
 * - L'auteur est joint en EAGER sur toutes les listes pour éviter le N+1 en affichage Twig
 *
 * Compatibilité base de données :
 * - Les fonctions TIMESTAMPDIFF() et POWER() sont natives MySQL / MariaDB. 
 */
class PostRepository extends ServiceEntityRepository implements PostRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    /**
     * ⚠️ IMPORTANT :
     * Cette méthode existe déjà dans ServiceEntityRepository,
     * on la redéclare uniquement pour l’IDE (pas nécessaire en runtime).
     */

    /**
     * @param int|string|null $id
     * @param int|null $lockMode
     * @param int|string|null $lockVersion
     */
    public function find($id, $lockMode = null, $lockVersion = null): ?Post
    {
        return parent::find($id, $lockMode, $lockVersion);
    }

    // ======================================================
    // LISTES PUBLIQUES
    // ======================================================

    public function findLatestPosts(int $limit = 10): array
    {
        return $this->createLatestPostsQueryBuilder()
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    private function createLatestPostsQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')
            ->addSelect('a')
            ->where('p.status = :status')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('status', ContentStatus::PUBLISHED)
            ->orderBy('p.createdAt', 'DESC');
    }

    // ======================================================
    // CLASSEMENTS ÉDITORIAUX
    // ======================================================

    public function findTrendingPosts(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')
            ->addSelect('a')
            ->leftJoin('p.votes', 'v')
            ->where('p.status = :status')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('status', ContentStatus::PUBLISHED)
            ->addSelect('
                (
                    SUM(CASE WHEN v.type IN (:humourTypes) THEN 1 ELSE 0 END) * 3
                    - SUM(CASE WHEN v.type = :angry THEN 1 ELSE 0 END) * 1.5
                    + COUNT(v.id) * 0.5
                )
                / POWER(
                    TIMESTAMPDIFF(HOUR, p.createdAt, CURRENT_TIMESTAMP()) + 6,
                    1.2
                ) AS HIDDEN rankingScore
            ')
            ->groupBy('p.id, a.id')
            ->orderBy('rankingScore', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('humourTypes', [
                VoteType::LAUGH->value,
                VoteType::DISILLUSIONED->value
            ])
            ->setParameter('angry', VoteType::ANGRY->value)
            ->getQuery()
            ->getResult();
    }

    public function findLegendPosts(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')
            ->addSelect('a')
            ->leftJoin('p.votes', 'v')
            ->where('p.status = :status')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('status', ContentStatus::PUBLISHED)
            ->addSelect('
                SUM(CASE WHEN v.type IN (:humourTypes) THEN 1 ELSE 0 END) * 2 AS HIDDEN rankingScore
            ')
            ->groupBy('p.id, a.id')
            ->orderBy('rankingScore', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('humourTypes', [
                VoteType::LAUGH->value,
                VoteType::DISILLUSIONED->value
            ])
            ->getQuery()
            ->getResult();
    }

    public function findAutoHiddenPendingPosts(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')
            ->addSelect('a')
            ->where('p.status = :status')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('status', ContentStatus::AUTO_HIDDEN)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findVisibleByAuthor(User $author, int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')
            ->addSelect('a')
            ->where('p.author = :author')
            ->andWhere('p.status = :status')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('author', $author)
            ->setParameter('status', ContentStatus::PUBLISHED)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countVisible(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.status = :status')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('status', ContentStatus::PUBLISHED)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
