<?php

declare(strict_types=1);

namespace App\Application\Dto;

/**
 * DTO pour la création d'un commentaire.
 */
final class CreateCommentDto
{
    public function __construct(
        public string $content = ''
    ) {}
}