<?php

declare(strict_types=1);

namespace App\Application\Formatter;

use App\Domain\Enum\VoteType;

/**
 * Formatter responsable de la présentation des VoteType (UI, Twig, formulaires).
 *
 * Gardé léger pour un DDD Light.
 */
final readonly class VoteTypeFormatter
{
    /**
     * Emoji associé au type de vote.
     */
    public function emoji(VoteType $voteType): string
    {
        return match ($voteType) {
            VoteType::LAUGH         => '😂',
            VoteType::ANGRY         => '😡',
            VoteType::DISILLUSIONED => '😏',
        };
    }

    /**
     * Label en français.
     */
    public function label(VoteType $voteType): string
    {
        return match ($voteType) {
            VoteType::LAUGH         => 'Trop drôle',
            VoteType::ANGRY         => 'Énervant',
            VoteType::DISILLUSIONED => 'Tellement vrai…',
        };
    }

    /**
     * Label complet : emoji + texte (très utile pour Twig et formulaires).
     */
    public function displayLabel(VoteType $voteType): string
    {
        return $this->emoji($voteType) . ' ' . $this->label($voteType);
    }

    /**
     * Clé de traduction pour le système i18n.
     */
    public function labelKey(VoteType $voteType): string
    {
        return match ($voteType) {
            VoteType::LAUGH         => 'vote.laugh',
            VoteType::ANGRY         => 'vote.angry',
            VoteType::DISILLUSIONED => 'vote.disillusioned',
        };
    }

    /**
     * Tableau pour Symfony Forms (label => value).
     * À utiliser dans tes FormTypes ou VoteController.
     */
    public function formChoices(): array
    {
        $choices = [];
        foreach (VoteType::cases() as $case) {
            $choices[$this->displayLabel($case)] = $case->value;
        }
        return $choices;
    }
}