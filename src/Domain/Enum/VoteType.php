<?php

declare(strict_types=1);

namespace App\Domain\Enum;

/**
 * Types de votes/réactions possibles sur un Post.
 *
 * Cet enum fait partie du langage ubiquitaire du domaine.
 * Utilisé pour le vote utilisateur et le calcul du reactionScore.
 */
enum VoteType: string
{
    case LAUGH         = 'laugh';
    case ANGRY         = 'angry';
    case DISILLUSIONED = 'disillusioned';

    /**
     * Impact signé sur le reactionScore du Post.
     *
     * LAUGH         → +3 (très positif)
     * DISILLUSIONED → +2 (fort engagement ironique)
     * ANGRY         → -1 (négatif)
     *
     *  Si ces valeurs changent, penser à recalculer les scores existants.
     */
    public function scoreImpact(): int
    {
        return match ($this) {
            self::LAUGH         => 3,
            self::DISILLUSIONED => 2,
            self::ANGRY         => -1,
        };
    }

    /**
     * Retourne toutes les valeurs string de l'enum.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Tableau pour les formulaires Symfony (label => value).
     * Version simple en attendant l'utilisation du VoteTypeFormatter.
     */
    public static function formChoices(): array
    {
        return [
            '😂 Trop drôle'       => self::LAUGH->value,
            '😡 Énervant'         => self::ANGRY->value,
            '😏 Tellement vrai…'  => self::DISILLUSIONED->value,
        ];
    }

    /**
     * Conversion sécurisée depuis une string.
     */
    public static function fromString(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new \InvalidArgumentException(sprintf(
                'Valeur invalide pour VoteType : "%s"', 
                $value
            ));
    }
}