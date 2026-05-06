<?php

declare(strict_types=1);

namespace App\Application\Dto;

/**
 * DTO pour la création d'un Post.
 * Utilisé entre le Formulaire et le Service.
 */
final class CreatePostDto
{
    public function __construct(
        public string $title = '',
        public string $content = ''
    ) {}
}