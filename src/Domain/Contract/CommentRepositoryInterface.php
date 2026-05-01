<?php

declare(strict_types=1);

namespace App\Domain\Contract;


use App\Domain\Entity\Post;
use App\Domain\Entity\User;

interface CommentRepositoryInterface
{
    public function findVisibleCommentsByPost(Post $post): array;

    public function findAllCommentsByPost(Post $post): array;

    public function findAutoHiddenPendingComments(): array;

    public function countVisibleByPost(Post $post): int;

    public function findLatestByAuthor(User $author, int $limit = 5): array;
}