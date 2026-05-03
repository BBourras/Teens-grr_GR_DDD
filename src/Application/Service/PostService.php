<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Contract\ModerationServiceInterface;
use App\Domain\Contract\PostRepositoryInterface;
use App\Domain\Entity\Post;
use App\Domain\Entity\User;
use App\Domain\Enum\ContentStatus;
use App\Domain\Event\ContentStatusChangedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * PostService – Service métier pour la gestion des Posts.
 *
 * Responsabilités :
 * - Création et mise à jour des posts
 * - Délégation complète de la modération à ModerationService
 * - Fourniture des QueryBuilders et listes pour les controllers
 */
final class PostService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PostRepositoryInterface $postRepository,
        private readonly ModerationServiceInterface $moderationService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function createPost(string $title, string $content, User $author): Post
    {
        $post = new Post($author, $title, $content);

        $this->em->persist($post);
        $this->em->flush();

        $this->eventDispatcher->dispatch(
            new ContentStatusChangedEvent(
                $post,
                ContentStatus::PUBLISHED,
                ContentStatus::PUBLISHED,
                $author
            )
        );

        return $post;
    }

    public function update(Post $post, string $newTitle, string $newContent): void
    {
        $post->updateContent($newTitle, $newContent);
        $this->em->flush();
    }

    public function deleteByAuthor(Post $post, User $author): void
    {
        $this->moderationService->deleteByAuthor($post, $author);
    }

    public function hardDelete(Post $post): void
    {
        $this->em->remove($post);
        $this->em->flush();
    }

    // ======================================================
    // LECTURE  (délègue au Repository)
    // ======================================================

    public function getLatestQueryBuilder(): QueryBuilder
    {
        return $this->postRepository->createLatestQueryBuilder();
    }

    public function getTrendingPosts(int $limit = 10): array
    {
        return $this->postRepository->findTrendingPosts($limit);
    }

    public function getLegendPosts(int $limit = 10): array
    {
        return $this->postRepository->findLegendPosts($limit);
    }

    public function getAutoHiddenPendingPosts(): array
    {
        return $this->postRepository->findAutoHiddenPendingPosts();
    }

    public function getVisiblePostsByAuthor(User $author, int $limit = 10): array
    {
        return $this->postRepository->findVisibleByAuthor($author, $limit);
    }
}