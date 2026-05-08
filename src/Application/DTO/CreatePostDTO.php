<?php

declare(strict_types=1);

namespace App\Application\Dto;

/**
 * DTO pour la création d'un Post.
 *
 * Utilisé entre le formulaire de création
 * et le PostService.
 */
final class CreatePostDto
{
    public function __construct(
        public string $title = '',
        public string $content = '',
    ) {}
}