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
    // LISTES PUBLIQUES
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

    public function findTrendingPosts(int $limit = 10): array
    {
        $limit = max(1, (int) $limit);

        $conn = $this->getEntityManager()->getConnection();

        // IMPORTANT :
        // On n'utilise pas de paramètre bindé dans LIMIT (MySQL ne le supporte pas correctement)
        $sql = "
            SELECT 
                p.id, p.title, p.content, p.created_at, p.status, p.reaction_score,
                a.id AS author_id, a.username,
                (
                    SUM(CASE WHEN v.type IN ('laugh', 'disillusioned') THEN 1 ELSE 0 END) * 3
                    - SUM(CASE WHEN v.type = 'angry' THEN 1 ELSE 0 END) * 1.5
                    + COUNT(v.id) * 0.5
                ) / POW(TIMESTAMPDIFF(HOUR, p.created_at, NOW()) + 6, 1.2) AS rankingScore
            FROM post p
            LEFT JOIN user a ON a.id = p.author_id
            LEFT JOIN vote v ON v.post_id = p.id
            WHERE p.status = 'published' AND p.deleted_at IS NULL
            GROUP BY p.id, a.id
            ORDER BY rankingScore DESC
            LIMIT $limit
        ";

        return $conn->executeQuery($sql)->fetchAllAssociative();
    }

    /**
     * Legend Posts : les posts qui restent excellents sur la durée.
     *
     * Algorithme :
     * - Score basé principalement sur les votes positifs
     * - Décroissance temporelle très lente (par jour)
     */
    public function findLegendPosts(int $limit = 10): array
    {
        $limit = max(1, (int) $limit);

        $conn = $this->getEntityManager()->getConnection();

        // IMPORTANT :
        // Même correction que pour trending : LIMIT direct (pas de paramètre bindé)
        $sql = "
            SELECT 
                p.id, p.title, p.content, p.created_at, p.status, p.reaction_score,
                a.id AS author_id, a.username,
                (
                    SUM(CASE WHEN v.type IN ('laugh', 'disillusioned') THEN 1 ELSE 0 END) * 2.5
                    + COUNT(v.id) * 0.3
                ) / POW(TIMESTAMPDIFF(DAY, p.created_at, NOW()) + 30, 0.8) AS rankingScore
            FROM post p
            LEFT JOIN user a ON a.id = p.author_id
            LEFT JOIN vote v ON v.post_id = p.id
            WHERE p.status = 'published' AND p.deleted_at IS NULL
            GROUP BY p.id, a.id
            ORDER BY rankingScore DESC
            LIMIT $limit
        ";

        return $conn->executeQuery($sql)->fetchAllAssociative();
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