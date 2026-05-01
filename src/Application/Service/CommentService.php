<?php

declare(strict_types=1);


namespace App\Application\Service;

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
 * CommentService – Gestion métier des commentaires.
 *
 * Responsabilités :
 * - Création de commentaires
 * - Suppression par l’auteur (via modération)
 * - Lecture des commentaires visibles / tous
 *
 * Toute action de modération (masquage, suppression) est déléguée à ModerationService.
 */
final class CommentService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CommentRepositoryInterface $commentRepository,
        private readonly ModerationServiceInterface $moderationService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Crée un nouveau commentaire publié sur un post.
     */
    public function createComment(string $content, User $author, Post $post): Comment
    {
        $comment = new Comment($author, $post, $content); // constructeur riche

        $this->em->wrapInTransaction(function () use ($comment) {
            $this->em->persist($comment);
        });

        $this->em->flush();

        // Événement pour cohérence avec le reste du domaine
        $this->eventDispatcher->dispatch(
            new ContentStatusChangedEvent(
                $comment,
                ContentStatus::PUBLISHED,
                ContentStatus::PUBLISHED,
                $author,
                null,
                'Création du commentaire'
            )
        );

        return $comment;
    }

    /**
     * Suppression du commentaire par son auteur.
     * L’action est tracée via ModerationService (AUTHOR_DELETE).
     */
    public function deleteByAuthor(Comment $comment, User $author): void
    {
        $this->moderationService->deleteByAuthor($comment, $author);
    }

    /**
     * Suppression physique définitive (usage très rare, réservé admin).
     */
    public function hardDelete(Comment $comment): void
    {
        $this->em->wrapInTransaction(function () use ($comment) {
            $this->em->remove($comment);
        });

        $this->em->flush();
    }

    // ======================================================
    // LECTURE
    // ======================================================

    /**
     * Retourne uniquement les commentaires visibles d’un post.
     */
    public function getVisibleCommentsByPost(Post $post): array
    {
        return $this->commentRepository->findVisibleCommentsByPost($post);
    }

    /**
     * Retourne tous les commentaires d’un post (usage modérateur).
     */
    public function getAllCommentsByPost(Post $post): array
    {
        return $this->commentRepository->findAllCommentsByPost($post);
    }

    /**
     * Retourne les commentaires AUTO_HIDDEN en attente de décision manuelle.
     */
    public function getAutoHiddenPendingComments(): array
    {
        return $this->commentRepository->findAutoHiddenPendingComments();
    }

    /**
     * Compte les commentaires visibles d’un post (utile pour l’affichage).
     */
    public function countVisibleByPost(Post $post): int
    {
        return $this->commentRepository->countVisibleByPost($post);
    }
}