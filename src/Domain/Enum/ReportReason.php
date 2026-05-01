<?php

declare(strict_types=1);

namespace App\Domain\Enum;

/**
 * Raisons possibles de signalement d'un Post ou d'un Commentaire.
 */
enum ReportReason: string
{
    case SPAM           = 'spam';
    case HARASSMENT     = 'harassment';
    case OFF_TOPIC      = 'off_topic';
    case HATE_SPEECH    = 'hate_speech';
    case MISINFORMATION = 'misinformation';
    case INAPPROPRIATE  = 'inappropriate';
    case OTHER          = 'other';

    /**
     * Indique si cette raison de signalement nécessite une revue prioritaire par un modérateur.
     */
    public function requiresModeratorReview(): bool
    {
        return match ($this) {
            self::HARASSMENT,
            self::HATE_SPEECH,
            self::INAPPROPRIATE => true,
            default             => false,
        };
    }

    /**
     * Retourne toutes les valeurs string de l'enum.
     */
    public static function values(): array
    {
        return array_map(static fn(self $reason) => $reason->value, self::cases());
    }

    /**
     * Tableau pour les formulaires Symfony (label => value).
     */
    public static function formChoices(): array
    {
        return [
            'Spam'                => self::SPAM->value,
            'Harcèlement'         => self::HARASSMENT->value,
            'Hors-sujet'          => self::OFF_TOPIC->value,
            'Discours haineux'    => self::HATE_SPEECH->value,
            'Désinformation'      => self::MISINFORMATION->value,
            'Contenu inapproprié' => self::INAPPROPRIATE->value,
            'Autre'               => self::OTHER->value,
        ];
    }

    /**
     * Conversion sécurisée depuis une string.
     */
    public static function fromString(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new \InvalidArgumentException(sprintf(
                'Valeur invalide pour ReportReason : "%s"', 
                $value
            ));
    }
}