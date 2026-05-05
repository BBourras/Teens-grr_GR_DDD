<?php

declare(strict_types=1);

namespace App\Application\DTO;

final class CreatePostDTO
{
    public ?string $title = null;
    public ?string $content = null;
}