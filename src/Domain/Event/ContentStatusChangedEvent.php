<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\Contract\ModeratableContentInterface;
use App\Domain\Entity\User;
use App\Domain\Enum\ContentStatus;
use App\Domain\Enum\ModerationActionType;

/**
 * Événement domaine déclenché à chaque changement de statut d’un Post ou Comment.
 *
 * C’est le point central pour toute réaction métier après une action de modération :
 * - Mise à jour des compteurs
 * - Notifications
 * - Purge de cache
 * - Historique avancé
 */
final readonly class ContentStatusChangedEvent
{
    public function __construct(
        private ModeratableContentInterface $content,
        private ContentStatus $oldStatus,
        private ContentStatus $newStatus,
        private ?User $actor = null,
        private ?ModerationActionType $actionType = null,
        private \DateTimeImmutable $occurredOn = new \DateTimeImmutable()
    ) {}

    public function getContent(): ModeratableContentInterface { return $this->content; }
    public function getOldStatus(): ContentStatus { return $this->oldStatus; }
    public function getNewStatus(): ContentStatus { return $this->newStatus; }
    public function getActor(): ?User { return $this->actor; }
    public function getActionType(): ?ModerationActionType { return $this->actionType; }
    public function getOccurredOn(): \DateTimeImmutable { return $this->occurredOn; }

    public function isNowVisible(): bool { return $this->newStatus === ContentStatus::PUBLISHED; }
    public function isDeletion(): bool { return $this->newStatus === ContentStatus::DELETED; }
    public function isRestoration(): bool { return $this->oldStatus->isHidden() && $this->newStatus === ContentStatus::PUBLISHED; }
}