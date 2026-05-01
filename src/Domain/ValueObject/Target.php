<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Contract\ModeratableContentInterface;

/**
 * Value Object représentant une cible de modération.
 *
 * ---------------------------------------------------------
 * RÈGLE ARCHITECTURALE :
 * - Aucun couplage Doctrine (pas d'entité Post/Comment ici)
 * - Représentation minimale et stable d'une cible métier
 * - Utilisable dans services, events, logs, UI
 *
 * ---------------------------------------------------------
 * CONTRAT :
 * Une Target est soit :
 * - un POST (type = post)
 * - un COMMENTAIRE (type = comment)
 *
 * jamais les deux, jamais aucun.
 */
final class Target
{
    private const TYPE_POST = 'post';
    private const TYPE_COMMENT = 'comment';

    private function __construct(
        private readonly string $type,
        private readonly int $id
    ) {}

    /**
     * Création d'une Target à partir d'une entité métier modérable.
     */
    public static function fromContent(ModeratableContentInterface $content): self
    {
        return match ($content->getTargetType()) {
            self::TYPE_POST => new self(self::TYPE_POST, $content->getId()),
            self::TYPE_COMMENT => new self(self::TYPE_COMMENT, $content->getId()),
            default => throw new \InvalidArgumentException('Unsupported target type'),
        };
    }

    /**
     * Factory directe (utile pour services / logs / events).
     */
    public static function post(int $id): self
    {
        return new self(self::TYPE_POST, $id);
    }

    public static function comment(int $id): self
    {
        return new self(self::TYPE_COMMENT, $id);
    }

    // ======================================================
    // HELPERS MÉTIER
    // ======================================================

    public function isPost(): bool
    {
        return $this->type === self::TYPE_POST;
    }

    public function isComment(): bool
    {
        return $this->type === self::TYPE_COMMENT;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getId(): int
    {
        return $this->id;
    }

    // ======================================================
    // SERIALISATION SIMPLE (utile logs / audit / queue)
    // ======================================================

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id'   => $this->id,
        ];
    }

    public function __toString(): string
    {
        return $this->type . ':' . $this->id;
    }
}