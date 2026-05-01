<?php

declare(strict_types=1);

namespace App\Domain\Contract;

use App\Domain\Entity\Post;
use App\Domain\Entity\User;
use App\Domain\Entity\Vote;
use DateTimeInterface;

interface VoteRepositoryInterface
{
    public function findOneByUserAndPost(User $user, Post $post): ?Vote;

    public function findOneByGuestAndPost(string $guestKey, Post $post): ?Vote;

    public function countByPost(Post $post): int;

    /**
     * @return Vote[]
     */
    public function findAllByPost(Post $post): array;

    /**
     * Retourne le nombre de votes par type pour un post.
     *
     * @return array<string, int>
     */
    public function findScoreByTypeForPost(Post $post): array;

    public function countRecentVotesByIpHash(
        Post $post,
        string $guestIpHash,
        DateTimeInterface $since
    ): int;
}