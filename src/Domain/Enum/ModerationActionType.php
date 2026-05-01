<?php

declare(strict_types=1);

namespace App\Domain\Enum;

/**
 * Types d'actions enregistrées dans ModerationActionLog.
 *
 * Permet de tracer qui a fait quoi et pourquoi sur les contenus modérés.
 */
enum ModerationActionType: string
{
    // Actions automatiques
    case AUTO_HIDE         = 'auto_hide';

    // Actions manuelles par modérateur
    case MODERATOR_HIDE    = 'moderator_hide';
    case RESTORE           = 'restore';
    case MODERATOR_DELETE  = 'moderator_delete';
    case REPORTS_CONFIRMED = 'reports_confirmed';
    case REPORTS_REJECTED  = 'reports_rejected';

    // Action par l'auteur
    case AUTHOR_DELETE     = 'author_delete';

    // Action informationnelle
    case REPORT_CREATED    = 'report_created';

    /**
     * Action déclenchée automatiquement par le système.
     */
    public function isAutomatic(): bool
    {
        return $this === self::AUTO_HIDE;
    }

    /**
     * Action réalisée par un modérateur ou administrateur.
     */
    public function isModerationAction(): bool
    {
        return in_array($this, [
            self::MODERATOR_HIDE,
            self::RESTORE,
            self::MODERATOR_DELETE,
            self::REPORTS_CONFIRMED,
            self::REPORTS_REJECTED,
        ], true);
    }

    /**
     * Action réalisée par l'auteur du contenu.
     */
    public function isAuthorAction(): bool
    {
        return $this === self::AUTHOR_DELETE;
    }

    /**
     * L'action aboutit à une suppression.
     */
    public function isDelete(): bool
    {
        return in_array($this, [self::AUTHOR_DELETE, self::MODERATOR_DELETE], true);
    }

    /**
     * L'action restaure le contenu.
     */
    public function isRestore(): bool
    {
        return in_array($this, [self::RESTORE, self::REPORTS_REJECTED], true);
    }

    /**
     * Retourne toutes les valeurs string de l'enum.
     */
    public static function values(): array
    {
        return array_map(static fn(self $action) => $action->value, self::cases());
    }

    /**
     * Conversion sécurisée depuis une string.
     */
    public static function fromString(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new \InvalidArgumentException(sprintf(
                'Valeur invalide pour ModerationActionType : "%s"', 
                $value
            ));
    }
}