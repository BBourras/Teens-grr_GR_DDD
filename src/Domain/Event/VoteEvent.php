<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\Entity\Post;
use App\Domain\Entity\Vote;
use App\Domain\Enum\VoteType;

/**
 * Événement domaine émis lors de toute action sur un vote.
 */
final readonly class VoteEvent
{
    public const CREATED = 'vote.created';
    public const UPDATED = 'vote.updated';
    public const REMOVED = 'vote.removed';

    public function __construct(
        private string $eventName,
        private Post $post,
        private Vote $vote,
        private ?VoteType $oldType = null,
        private ?VoteType $newType = null,
        private readonly \DateTimeImmutable $occurredOn = new \DateTimeImmutable()
    ) {}

    public function getEventName(): string { return $this->eventName; }
    public function getPost(): Post { return $this->post; }
    public function getVote(): Vote { return $this->vote; }
    public function getOldType(): ?VoteType { return $this->oldType; }
    public function getNewType(): ?VoteType { return $this->newType; }
    public function getOccurredOn(): \DateTimeImmutable { return $this->occurredOn; }

    public function isCreation(): bool { return $this->eventName === self::CREATED; }
    public function isUpdate(): bool   { return $this->eventName === self::UPDATED; }
    public function isRemoval(): bool  { return $this->eventName === self::REMOVED; }

    public function getScoreDelta(): int
    {
        $oldImpact = $this->oldType?->scoreImpact() ?? 0;
        $newImpact = $this->newType?->scoreImpact() ?? 0;
        return $newImpact - $oldImpact;
    }
}

/**
 * Événement domaine émis lors de toute action sur un vote.
 *
 * Écouté principalement par VoteScoreListener pour mettre à jour le reactionScore.
 */


    // Raccourcis métier utiles pour les listeners