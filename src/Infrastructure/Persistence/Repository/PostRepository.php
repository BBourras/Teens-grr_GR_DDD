<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Contract\PostRepositoryInterface;
use App\Domain\Entity\Post;
use App\Domain\Entity\User;
use App\Domain\Enum\ContentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository principal des Posts.
 *
 * Stratégie :
 * - DQL + QueryBuilder pour les requêtes simples et paginées
 * - SQL natif pour les classements complexes (Trending & Legend) afin d'utiliser
 *   TIMESTAMPDIFF + POW de façon fiable
 *
 * Optimisations :
 * - Jointure systématique sur l'auteur pour éviter le N+1 dans Twig
 * - Calcul du ranking côté base de données
 */

class PostRepository extends ServiceEntityRepository implements PostRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    // ======================================================
    // LISTES PUBLIQUES  (DQL)
    // ======================================================

    public function findLatestPosts(int $limit = 10): array
    {
        return $this->createLatestQueryBuilder()
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * QueryBuilder de base pour les posts visibles.
     * Utilisé principalement avec KnpPaginator sur la home et la page "recent".
     */
    public function createLatestQueryBuilder(): QueryBuilder
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
    // TRENDING & LEGEND (SQL natif → hydratation en entités)
    // ======================================================

        public function findTrendingPosts(int $limit = 10): array
    {
        $limit = min(50, max(1, (int) $limit));   // ← Sécurisé anti-abus (nbre limité de résultats)

        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT p.id 
            FROM post p
            WHERE p.status = 'published' 
              AND p.deleted_at IS NULL
            ORDER BY 
                (
                    (SELECT COUNT(*) FROM vote v 
                     WHERE v.post_id = p.id AND v.type IN ('laugh','disillusioned')) * 3
                    - (SELECT COUNT(*) FROM vote v 
                       WHERE v.post_id = p.id AND v.type = 'angry') * 1.5
                    + (SELECT COUNT(*) FROM vote v WHERE v.post_id = p.id) * 0.5
                ) 
                / POW(TIMESTAMPDIFF(HOUR, p.created_at, NOW()) + 6, 1.2) DESC
            LIMIT " . $limit;

        $ids = array_column(
            $conn->executeQuery($sql)->fetchAllAssociative(),
            'id'
        );

        if (empty($ids)) {
            return [];
        }

        $posts = $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')
            ->addSelect('a')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        // Conservation de l'ordre du classement SQL
        $ordered = [];
        $position = array_flip($ids);
        foreach ($posts as $post) {
            $ordered[$position[$post->getId()]] = $post;
        }
        ksort($ordered);

        return array_values($ordered);
    }

    public function findLegendPosts(int $limit = 10): array
    {
        $limit = min(70, max(1, (int) $limit));   // ← Sécurisé anti-abus (nbre limité résultats)

        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT p.id 
            FROM post p
            WHERE p.status = 'published' 
              AND p.deleted_at IS NULL
            ORDER BY 
                (
                    (SELECT COUNT(*) FROM vote v 
                     WHERE v.post_id = p.id AND v.type IN ('laugh','disillusioned')) * 2.5
                    + (SELECT COUNT(*) FROM vote v WHERE v.post_id = p.id) * 0.3
                ) 
                / POW(TIMESTAMPDIFF(DAY, p.created_at, NOW()) + 30, 0.8) DESC
            LIMIT " . $limit;

        $ids = array_column(
            $conn->executeQuery($sql)->fetchAllAssociative(),
            'id'
        );

        if (empty($ids)) {
            return [];
        }

        $posts = $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')
            ->addSelect('a')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $ordered = [];
        $position = array_flip($ids);
        foreach ($posts as $post) {
            $ordered[$position[$post->getId()]] = $post;
        }
        ksort($ordered);

        return array_values($ordered);
    }

    // ======================================================
    // MODÉRATION
    // ======================================================

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

    // profil utilisateur
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

    // ======================================================
    // STATISTIQUES
    // ======================================================

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
