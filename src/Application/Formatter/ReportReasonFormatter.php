<?php

declare(strict_types=1);

namespace App\Application\Formatter;

use App\Domain\Enum\ReportReason;

/**
 * Formatter pour les raisons de signalement.
 */
final readonly class ReportReasonFormatter
{
    public function label(ReportReason|string $reason): string
    {
        if (is_string($reason)) {
            $reason = ReportReason::tryFrom($reason) ?? ReportReason::OTHER;
        }

        return match ($reason) {
            ReportReason::SPAM           => 'Spam',
            ReportReason::HARASSMENT     => 'Harcèlement',
            ReportReason::OFF_TOPIC      => 'Hors-sujet',
            ReportReason::HATE_SPEECH    => 'Discours haineux',
            ReportReason::MISINFORMATION => 'Désinformation',
            ReportReason::INAPPROPRIATE  => 'Contenu inapproprié',
            ReportReason::OTHER          => 'Autre',
        };
    }

    public function labelKey(ReportReason|string $reason): string
    {
        if (is_string($reason)) {
            $reason = ReportReason::tryFrom($reason) ?? ReportReason::OTHER;
        }

        return match ($reason) {
            ReportReason::SPAM           => 'report.reason.spam',
            ReportReason::HARASSMENT     => 'report.reason.harassment',
            ReportReason::OFF_TOPIC      => 'report.reason.off_topic',
            ReportReason::HATE_SPEECH    => 'report.reason.hate_speech',
            ReportReason::MISINFORMATION => 'report.reason.misinformation',
            ReportReason::INAPPROPRIATE  => 'report.reason.inappropriate',
            ReportReason::OTHER          => 'report.reason.other',
        };
    }

    public function formChoices(): array
    {
        $choices = [];
        foreach (ReportReason::cases() as $case) {
            $choices[$this->label($case)] = $case->value;
        }
        return $choices;
    }
}