<?php

declare(strict_types=1);

namespace App\Domain\Contract;

use App\Domain\Enum\ContentStatus;

/**
 * ModeratableContentInterface
 *
 * Interface commune à tous les contenus modérables (Post et Comment).
 * Permet un traitement polymorphe dans les services de modération.
 */
interface ModeratableContentInterface
{
    public function getId(): ?int;

    public function getStatusEnum(): ContentStatus;
    public function setStatus(ContentStatus $status): static;

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static;
    public function getDeletedAt(): ?\DateTimeImmutable;

    // Statut simplifié
    public function isVisible(): bool;
    public function isHidden(): bool;
    public function isDeleted(): bool;
    public function isModerated(): bool;

    /**
     * Retourne l'ID du Post principal auquel ce contenu est rattaché.
     * 
     * - Pour un Post → retourne son propre ID
     * - Pour un Comment → retourne l'ID du Post parent
     */
    public function getRelatedPostId(): int;

    /**
     * Type utilisé par Target et les factories.
     */
    public function getTargetType(): string;

    // Signalements
    public function incrementReportCount(): static;
    public function getReportCount(): int;

    // Affichage
    public function label(): string;
    public function labelKey(): string;
}