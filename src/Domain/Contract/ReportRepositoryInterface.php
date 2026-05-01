<?php

declare(strict_types=1);

namespace App\Domain\Contract;

use App\Domain\Entity\Comment;
use App\Domain\Entity\Post;
use App\Domain\Entity\Report;
use App\Domain\Entity\User;
use App\Domain\Contract\ModeratableContentInterface;

interface ReportRepositoryInterface
{
    public function hasAlreadyReportedPost(Post $post, User $user): bool;

    public function hasAlreadyReportedComment(Comment $comment, User $user): bool;

    /**
     * @return Report[]
     */
    public function findAllByPost(Post $post): array;

    /**
     * @return Report[]
     */
    public function findAllByComment(Comment $comment): array;

    /**
     * Signalements en attente, triés par priorité.
     *
     * @return Report[]
     */
    public function findPendingReports(int $limit = 50): array;

    public function countByContent(ModeratableContentInterface $content): int;

    /**
     * @return Report[]
     */
    public function findRecent(int $limit = 20): array;
}