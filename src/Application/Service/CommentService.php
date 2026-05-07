<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Dto\CreateCommentDto;
use App\Domain\Contract\CommentRepositoryInterface;
use App\Domain\Contract\ModerationServiceInterface;
use App\Domain\Entity\Comment;
use App\Domain\Entity\Post;
use App\Domain\Entity\User;
use App\Domain\Enum\ContentStatus;
use App\Domain\Event\ContentStatusChangedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * CommentService – Service métier pour la gestion des commentaires.
 *
 * Responsabilités :
 * - Création de commentaires (via DTO pour cohérence DDD)
 * - Suppression par l’auteur (déléguée à ModerationService)
 * - Lecture des commentaires (visibles ou tous)
 *
 * Toute logique de modération est déléguée à ModerationService.
 */
final class CommentService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CommentRepositoryInterface $commentRepository,
        private readonly ModerationServiceInterface $moderationService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    // ======================================================
    // COMMANDES (Write)
    // ======================================================

    /**
     * Crée un nouveau commentaire avec le statut PUBLISHED par défaut.
     */
    public function createComment(CreateCommentDto $dto, User $author, Post $post): Comment
    {
        $comment = new Comment($author, $post, $dto->content);

        $this->em->persist($comment);
        $this->em->flush();

        $this->eventDispatcher->dispatch(
            new ContentStatusChangedEvent(
                $comment,
                ContentStatus::PUBLISHED,
                ContentStatus::PUBLISHED,
                $author
            )
        );

        return $comment;
    }

    /**
     * Suppression par l’auteur (soft delete + historique de modération).
     */
    public function deleteByAuthor(Comment $comment, User $author): void
    {
        $this->moderationService->deleteByAuthor($comment, $author);
    }

    /**
     * Suppression physique définitive (réservée aux administrateurs).
     */
    public function hardDelete(Comment $comment): void
    {
        $this->em->remove($comment);
        $this->em->flush();
    }

    // ======================================================
    // QUERIES (Read)
    // ======================================================

    /**
     * Retourne uniquement les commentaires visibles (PUBLISHED).
     */
    public function getVisibleCommentsByPost(Post $post): array
    {
        return $this->commentRepository->findVisibleCommentsByPost($post);
    }

    /**
     * Retourne tous les commentaires d’un post (utilisé en modération).
     */
    public function getAllCommentsByPost(Post $post): array
    {
        return $this->commentRepository->findAllCommentsByPost($post);
    }

    /**
     * Retourne les commentaires masqués automatiquement en attente de validation manuelle.
     */
    public function getAutoHiddenPendingComments(): array
    {
        return $this->commentRepository->findAutoHiddenPendingComments();
    }

    /**
     * Compte le nombre de commentaires visibles sur un post.
     */
    public function countVisibleByPost(Post $post): int
    {
        return $this->commentRepository->countVisibleByPost($post);
    }
}