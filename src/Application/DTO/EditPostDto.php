<?php

declare(strict_types=1);

namespace App\Application\Dto;

use App\Domain\Entity\Post;

/**
 * DTO pour la modification d'un Post.
 *
 * Utilisé entre le formulaire d'édition
 * et le PostService.
 */
final class EditPostDto
{
    public function __construct(
        public string $title = '',
        public string $content = '',
    ) {}

    /**
     * Hydrate le DTO à partir d'une entité Post.
     */
    public static function fromEntity(Post $post): self
    {
        return new self(
            title: $post->getTitle(),
            content: $post->getContent(),
        );
    }
}