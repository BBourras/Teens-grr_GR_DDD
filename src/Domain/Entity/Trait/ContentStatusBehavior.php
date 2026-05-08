<?php

declare(strict_types=1);

namespace App\Domain\Entity\Trait;

use App\Domain\Enum\ContentStatus;
use App\Application\Formatter\ContentStatusFormatter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Trait ContentStatusBehavior
 *
 * Comportement commun pour gérer le statut des entités modérables (Post & Comment).
 * Implémente ModeratableContentInterface.
 */
trait ContentStatusBehavior
{
    #[ORM\Column(enumType: ContentStatus::class)]
    private ContentStatus $status = ContentStatus::PUBLISHED;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $reportCount = 0;

    private readonly ContentStatusFormatter $formatter;

    public function __construct()
    {
        $this->formatter = new ContentStatusFormatter();
    }

    // ======================================================
    // ModeratableContentInterface
    // ======================================================

    public function getStatusEnum(): ContentStatus
    {
        return $this->status;
    }

    public function setStatus(ContentStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isVisible(): bool
    {
        return $this->status === ContentStatus::PUBLISHED;
    }

    public function isHidden(): bool
    {
        return $this->status === ContentStatus::AUTO_HIDDEN 
            || $this->status === ContentStatus::HIDDEN_BY_MODERATOR;
    }

    public function isDeleted(): bool
    {
        return $this->status === ContentStatus::DELETED;
    }

    public function isModerated(): bool
    {
        return !$this->isVisible();
    }

    public function isAutoModerated(): bool
    {
        return $this->status === ContentStatus::AUTO_HIDDEN;
    }

    public function isManuallyModerated(): bool
    {
        return $this->status === ContentStatus::HIDDEN_BY_MODERATOR 
            || $this->status === ContentStatus::DELETED;
    }

    public function incrementReportCount(): static
    {
        $this->reportCount++;
        return $this;
    }

    public function getReportCount(): int
    {
        return $this->reportCount;
    }

    // Délégation au Formatter
    public function label(): string
    {
        return $this->formatter->label($this->status);
    }

    public function labelKey(): string
    {
        return $this->formatter->labelKey($this->status);
    }

    /**
     * Doit être implémentée dans Post et Comment.
     */
    abstract public function getRelatedPostId(): int;

    abstract public function getTargetType(): string;
}