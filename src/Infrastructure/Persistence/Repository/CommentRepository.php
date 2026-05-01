<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Contract\CommentRepositoryInterface;
use App\Domain\Entity\Comment;
use App\Domain\Entity\Post;
use App\Domain\Entity\User;
use App\Domain\Enum\ContentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository des commentaires.
 *
 * Responsabilités :
 * - Fournir les commentaires visibles pour l'affichage public
 * - Fournir tous les commentaires pour la modération
 * - Éviter le problème N+1 en joignant systématiquement l'auteur
 * - Respecter le statut et le soft-delete
 */
class CommentRepository extends ServiceEntityRepository implements CommentRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    /**
     * Retourne uniquement les commentaires PUBLISHED et non supprimés
     * d'un post donné, avec l'auteur pré-chargé (EXTRA_LAZY + join).
     *
     * Utilisé sur la page d'un post pour l'affichage public.
     */
    public function findVisibleCommentsByPost(Post $post): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.author', 'a')
            ->addSelect('a')
            ->where('c.post = :post')
            ->andWhere('c.status = :status')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('post', $post)
            ->setParameter('status', ContentStatus::PUBLISHED)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne TOUS les commentaires d'un post (tous statuts),
     * avec l'auteur pré-chargé.
     *
     * Réservé aux modérateurs et au dashboard.
     */
    public function findAllCommentsByPost(Post $post): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.author', 'a')
            ->addSelect('a')
            ->where('c.post = :post')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les commentaires masqués automatiquement (AUTO_HIDDEN)
     * et non supprimés, en attente de décision manuelle du modérateur.
     *
     * Utilisé dans ModerationController et ModerationService.
     */
    public function findAutoHiddenPendingComments(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.author', 'a')
            ->addSelect('a')
            ->where('c.status = :status')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('status', ContentStatus::AUTO_HIDDEN)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de commentaires visibles sur un post.
     * Utile pour afficher le compteur sans charger tous les commentaires.
     */
    public function countVisibleByPost(Post $post): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.post = :post')
            ->andWhere('c.status = :status')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('post', $post)
            ->setParameter('status', ContentStatus::PUBLISHED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Retourne les commentaires les plus récents d'un auteur
     * (utile pour le profil utilisateur ou le dashboard).
     */
    public function findLatestByAuthor(User $author, int $limit = 5): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.author', 'a')
            ->addSelect('a')
            ->where('c.author = :author')
            ->andWhere('c.status = :status')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('author', $author)
            ->setParameter('status', ContentStatus::PUBLISHED)
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}