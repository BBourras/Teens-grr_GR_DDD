<?php

declare(strict_types=1);

namespace App\Application\Formatter;

use App\Domain\Enum\ModerationActionType;

/**
 * Formatter pour les actions de modération (utilisé dans le dashboard modérateur).
 */
final readonly class ModerationActionTypeFormatter
{
    public function label(ModerationActionType $action): string
    {
        return match ($action) {
            ModerationActionType::AUTO_HIDE         => 'Masquage automatique',
            ModerationActionType::MODERATOR_HIDE    => 'Masquage manuel',
            ModerationActionType::RESTORE           => 'Restauration',
            ModerationActionType::MODERATOR_DELETE  => 'Suppression par modérateur',
            ModerationActionType::REPORTS_CONFIRMED => 'Signalements confirmés',
            ModerationActionType::REPORTS_REJECTED  => 'Signalements rejetés',
            ModerationActionType::AUTHOR_DELETE     => 'Suppression par l\'auteur',
            ModerationActionType::REPORT_CREATED    => 'Signalement créé',
        };
    }

    public function labelKey(ModerationActionType $action): string
    {
        return match ($action) {
            ModerationActionType::AUTO_HIDE         => 'moderation.action.auto_hide',
            ModerationActionType::MODERATOR_HIDE    => 'moderation.action.moderator_hide',
            ModerationActionType::RESTORE           => 'moderation.action.restore',
            ModerationActionType::MODERATOR_DELETE  => 'moderation.action.moderator_delete',
            ModerationActionType::REPORTS_CONFIRMED => 'moderation.action.reports_confirmed',
            ModerationActionType::REPORTS_REJECTED  => 'moderation.action.reports_rejected',
            ModerationActionType::AUTHOR_DELETE     => 'moderation.action.author_delete',
            ModerationActionType::REPORT_CREATED    => 'moderation.action.report_created',
        };
    }
}