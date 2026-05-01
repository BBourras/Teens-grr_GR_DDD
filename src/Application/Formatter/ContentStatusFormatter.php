<?php

declare(strict_types=1);

namespace App\Application\Formatter;

use App\Domain\Enum\ContentStatus;

/**
 * Formatter pour les statuts de contenu (Post et Comment).
 */
final readonly class ContentStatusFormatter
{
    public function label(ContentStatus $status): string
    {
        return match ($status) {
            ContentStatus::PUBLISHED           => 'Publié',
            ContentStatus::AUTO_HIDDEN         => 'Masqué automatiquement',
            ContentStatus::HIDDEN_BY_MODERATOR => 'Masqué par un modérateur',
            ContentStatus::DELETED             => 'Supprimé',
        };
    }

    public function labelKey(ContentStatus $status): string
    {
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