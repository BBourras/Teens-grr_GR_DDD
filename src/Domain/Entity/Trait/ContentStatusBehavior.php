<?php

declare(strict_types=1);

namespace App\Domain\Entity\Trait;

use App\Domain\Enum\ContentStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Trait ContentStatusBehavior.
 *
 * Partagé entre Post et Comment pour gérer le cycle de vie du statut
 * (PUBLISHED, AUTO_HIDDEN, HIDDEN_BY_MODERATOR, DELETED).
 *
 * Délègue la logique métier à l'enum ContentStatus.
 */
trait ContentStatusBehavior
{
    // ======================================================
    // MAPPING DOCTRINE
    // ======================================================

    #[ORM\Column(type: 'string', enumType: ContentStatus::class, length: 50)]
    protected ContentStatus $status = ContentStatus::PUBLISHED;

    #[ORM\Column(name: 'deleted_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    // ======================================================
    // LIFECYCLE CALLBACKS
    // ======================================================

    #[ORM\PreUpdate]
    public function onPreUpdateStatus(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ======================================================
    // MÉTHODES MÉTIER (déléguées à l'enum)
    // ======================================================

    public function isVisible(): bool
    {
        return $this->status->isVisible() && $this->deletedAt === null;
    }

    public function isHidden(): bool
    {
        return $this->status->isHidden();
    }

    public function isDeleted(): bool
    {
        return $this->status->isDeleted();
    }

    public function isModerated(): bool
    {
        return $this->status->isModerated();
    }

    public function isAutoModerated(): bool
    {
        return $this->status->isAutoModerated();
    }

    public function isManuallyModerated(): bool
    {
        return $this->status->isManuallyModerated();
    }

    public function markAsDeleted(): static
    {
        $this->status = ContentStatus::DELETED;
        $this->deletedAt = new \DateTimeImmutable();
        return $this;
    }

    // ======================================================
    // GETTERS / SETTERS
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

    public function getStatusValue(): string
    {
        return $this->status->value;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}