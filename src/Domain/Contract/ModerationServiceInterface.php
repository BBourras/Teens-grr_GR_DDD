<?php

declare(strict_types=1);

namespace App\Domain\Contract;

use App\Domain\Entity\User;

interface ModerationServiceInterface
{
    public function hideByModerator(
        ModeratableContentInterface $content,
        User $moderator,
        ?string $reason = null
    ): void;

    public function autoHide(ModeratableContentInterface $content): void;

    public function deleteByModerator(
        ModeratableContentInterface $content,
        User $moderator,
        ?string $reason = null
    ): void;

    public function deleteByAuthor(
        ModeratableContentInterface $content,
        User $author
    ): void;

    public function restore(
        ModeratableContentInterface $content,
        ?User $moderator = null,
        ?string $reason = null
    ): void;

    public function confirmAutoHide(
        ModeratableContentInterface $content,
        User $moderator,
        ?string $reason = null
    ): void;

    public function rejectReport(
        ModeratableContentInterface $content,
        User $moderator,
        ?string $reason = null
    ): void;
}