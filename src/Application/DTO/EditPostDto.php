<?php

declare(strict_types=1);

namespace App\Application\Dto;

use App\Domain\Entity\Post;

/**
 * DTO pour la modification d'un Post.
 *
 * Utilisé entre EditPostFormType et PostService.
 */
final class EditPostDto
{
    public function __construct(
        public string $title = '',
        public string $content = '',
    ) {}

    /**
     * Crée un DTO à partir d'une entité existante (pour l'édition).
     */
    public static function fromEntity(Post $post): self
    {
        return new self(
            title: $post->getTitle(),
            content: $post->getContent(),
        );
    }
}