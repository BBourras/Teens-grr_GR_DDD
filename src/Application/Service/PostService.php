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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Service métier pour la gestion des Posts.
 *
 * Responsabilités strictes :
 * - Création et mise à jour des posts
 * - Délégation totale de la modération à ModerationService
 * - Lecture des listes publiques et éditoriales
 *
 * Aucune logique de statut ou de masquage ne doit se trouver ici.
 */
final class PostService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PostRepositoryInterface $postRepository,
        private readonly ModerationServiceInterface $moderationService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Crée un nouveau post avec le statut PUBLISHED par défaut.
     */
    public function createPost(string $title, string $content, User $author): Post
    {
        $post = new Post($author, $title, $content);   // constructeur riche recommandé

        $this->em->persist($post);
        $this->em->flush();

        // Événement de création (optionnel mais utile pour cohérence)
        $this->eventDispatcher->dispatch(
            new ContentStatusChangedEvent(
                $post,
                ContentStatus::PUBLISHED,
                ContentStatus::PUBLISHED,
                $author,
                null
            )
        );

        return $post;
    }

    /**
     * Met à jour un post existant (titre/contenu uniquement).
     * Toute modification de statut passe obligatoirement par ModerationService.
     */
    public function update(Post $post, ?string $newTitle = null, ?string $newContent = null): void
    {
        if ($newTitle !== null || $newContent !== null) {
            $post->updateContent(
                $newTitle ?? $post->getTitle(),
                $newContent ?? $post->getContent()
            );
        }

        $this->em->flush();
    }

    /**
     * Suppression par l'auteur → passe par la modération pour tracer l'action.
     */
    public function deleteByAuthor(Post $post, User $author): void
    {
        $this->moderationService->deleteByAuthor($post, $author);
    }

    /**
     * Suppression physique définitive (très rare, usage admin uniquement).
     */
    public function hardDelete(Post $post): void
    {
        $this->em->remove($post);
        $this->em->flush();
    }

    // ======================================================
    // LECTURE – Délègue au Repository
    // ======================================================

    public function getLatestPosts(int $limit = 10): array
    {
        return $this->postRepository->findLatestPosts($limit);
    }

    public function getTrendingPosts(int $limit = 10): array
    {
        return $this->postRepository->findTrendingPosts($limit);
    }

    public function getLegendPosts(int $limit = 10): array
    {
        return $this->postRepository->findLegendPosts($limit);
    }

    public function getAutoHiddenPosts(): array
    {
        return $this->postRepository->findAutoHiddenPendingPosts();
    }

    /**
     * Méthode de commodité : posts visibles d'un auteur (profil utilisateur).
     */
    public function getVisiblePostsByAuthor(User $author, int $limit = 10): array
    {
        return $this->postRepository->findVisibleByAuthor($author, $limit);
    }
}