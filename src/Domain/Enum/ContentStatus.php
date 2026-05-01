<?php

declare(strict_types=1);

namespace App\Domain\Enum;

/**
 * Statuts de contenu pour Post et Comment.
 *
 * Cycle de vie : PUBLISHED → AUTO_HIDDEN → (PUBLISHED ou HIDDEN_BY_MODERATOR) → DELETED
 */
enum ContentStatus: string
{
    case PUBLISHED           = 'published';
    case AUTO_HIDDEN         = 'auto_hidden';
    case HIDDEN_BY_MODERATOR = 'hidden_by_moderator';
    case DELETED             = 'deleted';

    public function isVisible(): bool
    {
        return $this === self::PUBLISHED;
    }

    public function isHidden(): bool
    {
        return $this === self::AUTO_HIDDEN || $this === self::HIDDEN_BY_MODERATOR;
    }

    public function isDeleted(): bool
    {
        return $this === self::DELETED;
    }

    public function isModerated(): bool
    {
        return $this !== self::PUBLISHED;
    }

    public function isAutoModerated(): bool
    {
        return $this === self::AUTO_HIDDEN;
    }

    public function isManuallyModerated(): bool
    {
        return $this === self::HIDDEN_BY_MODERATOR || $this === self::DELETED;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Conversion sécurisée depuis une string.
     */
    public static function fromString(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new \InvalidArgumentException(sprintf(
                'Valeur invalide pour ContentStatus : "%s"', 
                $value
            ));
    }
}