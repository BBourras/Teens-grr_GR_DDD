<?php

declare(strict_types=1);

namespace App\Application\Formatter;

use App\Domain\Enum\ContentStatus;

/**
 * Formatter pour les statuts de contenu (Post, Comment, Logs).
 * Accepte à la fois l'enum et une string (cas des logs en base).
 */
final readonly class ContentStatusFormatter
{
    public function label(ContentStatus|string $status): string
    {
        if (is_string($status)) {
            $status = ContentStatus::tryFrom($status) ?? ContentStatus::PUBLISHED;
        }

        return match ($status) {
            ContentStatus::PUBLISHED           => 'Publié',
            ContentStatus::AUTO_HIDDEN         => 'Masqué automatiquement',
            ContentStatus::HIDDEN_BY_MODERATOR => 'Masqué par un modérateur',
            ContentStatus::DELETED             => 'Supprimé',
        };
    }

    public function labelKey(ContentStatus|string $status): string
    {
        if (is_string($status)) {
            $status = ContentStatus::tryFrom($status) ?? ContentStatus::PUBLISHED;
        }

        return match ($status) {
            ContentStatus::PUBLISHED           => 'content.status.published',
            ContentStatus::AUTO_HIDDEN         => 'content.status.auto_hidden',
            ContentStatus::HIDDEN_BY_MODERATOR => 'content.status.hidden_by_moderator',
            ContentStatus::DELETED             => 'content.status.deleted',
        };
    }

    public function formChoices(): array
    {
        $choices = [];
        foreach (ContentStatus::cases() as $case) {
            $choices[$this->label($case)] = $case->value;
        }
        return $choices;
    }
}