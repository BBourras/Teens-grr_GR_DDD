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
 * Toute action de modération est déléguée à ModerationService.
 */
final class CommentService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CommentRepositoryInterface $commentRepository,
        private readonly ModerationServiceInterface $moderationService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function createComment(string $content, User $author, Post $post): Comment
    {
        $comment = new Comment($author, $post, $content);

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

    public function deleteByAuthor(Comment $comment, User $author): void
    {
        $this->moderationService->deleteByAuthor($comment, $author);
    }

    public function hardDelete(Comment $comment): void
    {
        $this->em->remove($comment);
        $this->em->flush();
    }

    // ======================================================
    // LECTURE
    // ======================================================

    public function getVisibleCommentsByPost(Post $post): array
    {
        return $this->commentRepository->findVisibleCommentsByPost($post);
    }

    public function getAllCommentsByPost(Post $post): array
    {
        return $this->commentRepository->findAllCommentsByPost($post);
    }

    public function getAutoHiddenPendingComments(): array
    {
        return $this->commentRepository->findAutoHiddenPendingComments();
    }

    public function countVisibleByPost(Post $post): int
    {
        return $this->commentRepository->countVisibleByPost($post);
    }
}