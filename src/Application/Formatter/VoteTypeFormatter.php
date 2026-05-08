<?php

declare(strict_types=1);

namespace App\Application\Formatter;

use App\Domain\Enum\VoteType;

/**
 * Formatter pour les types de vote.
 */
final readonly class VoteTypeFormatter
{
    public function emoji(VoteType|string $voteType): string
    {
        if (is_string($voteType)) {
            $voteType = VoteType::tryFrom($voteType) ?? VoteType::LAUGH;
        }

        return match ($voteType) {
            VoteType::LAUGH         => '😂',
            VoteType::ANGRY         => '😡',
            VoteType::DISILLUSIONED => '😏',
        };
    }

    public function label(VoteType|string $voteType): string
    {
        if (is_string($voteType)) {
            $voteType = VoteType::tryFrom($voteType) ?? VoteType::LAUGH;
        }

        return match ($voteType) {
            VoteType::LAUGH         => 'Trop drôle',
            VoteType::ANGRY         => 'Énervant',
            VoteType::DISILLUSIONED => 'Désabusé et moqueur',
        };
    }

    public function displayLabel(VoteType|string $voteType): string
    {
        return $this->emoji($voteType) . ' ' . $this->label($voteType);
    }
}