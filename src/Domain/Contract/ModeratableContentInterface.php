<?php

declare(strict_types=1);

namespace App\Domain\Contract;

use App\Domain\Entity\Post;
use App\Domain\Entity\User;
use App\Domain\Enum\ContentStatus;

/**
 * Interface centrale pour tout contenu modérable (Post ET Comment).
 *
 * Permet un traitement uniforme dans ModerationService, ReportService,
 * Voters, Dashboard, etc. sans instanceof.
 */
interface ModeratableContentInterface
{
    // ======================================================
    // IDENTIFICATION
    // ======================================================

    public function getId(): ?int;

    public function getAuthor(): User;

    /**
     * Type de cible ('post' ou 'comment').
     */
    public function getTargetType(): string;

    /**
     * Retourne le Post parent :
     * - Post   → retourne $this
     * - Comment→ retourne le post associé
     */
    public function getPost(): Post;

    // ======================================================
    // STATUT & MODÉRATION
    // ======================================================

    public function getStatusEnum(): ContentStatus;

    public function setStatus(ContentStatus $status): static;

    public function isVisible(): bool;

    public function isHidden(): bool;

    public function isDeleted(): bool;

    public function isModerated(): bool;

    public function isAutoModerated(): bool;

    public function isManuallyModerated(): bool;

    public function markAsDeleted(): static;

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static;

    // ======================================================
    // COMPTEURS
    // ======================================================

    public function getReportCount(): int;

    public function incrementReportCount(int $by = 1): static;

    public function decrementReportCount(int $by = 1): static;
}