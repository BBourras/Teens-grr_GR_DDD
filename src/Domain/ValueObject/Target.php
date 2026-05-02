<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Contract\ModeratableContentInterface;
use App\Domain\Entity\Comment;
use App\Domain\Entity\Post;

/**
 * Value Object immutable représentant une cible de modération ou de signalement.
 *
 * RÈGLE ARCHITECTURALE :
 * - Aucun couplage direct avec Doctrine ou les entités dans les services/logs.
 * - Représentation minimale, stable et sécurisée d'une cible métier.
 * - Utilisable dans Services, Events, Factories, Logs, etc.
 */
final class Target
{
    private const TYPE_POST    = 'post';
    private const TYPE_COMMENT = 'comment';

    private function __construct(
        private readonly string $type,
        private readonly int $id,
        private readonly ?Post $post = null,
        private readonly ?Comment $comment = null,
    ) {}

    /**
     * Crée une Target à partir d'une entité modérable (Post ou Comment).
     */
    public static function fromContent(ModeratableContentInterface $content): self
    {
        return match ($content->getTargetType()) {
            self::TYPE_POST => new self(
                self::TYPE_POST,
                $content->getId() ?? throw new \InvalidArgumentException('Post ID cannot be null'),
                post: $content instanceof Post ? $content : null
            ),
            self::TYPE_COMMENT => new self(
                self::TYPE_COMMENT,
                $content->getId() ?? throw new \InvalidArgumentException('Comment ID cannot be null'),
                comment: $content instanceof Comment ? $content : null
            ),
            default => throw new \InvalidArgumentException('Unsupported target type: ' . $content->getTargetType()),
        };
    }

    public static function post(int $id, ?Post $post = null): self
    {
        return new self(self::TYPE_POST, $id, post: $post);
    }

    public static function comment(int $id, ?Comment $comment = null): self
    {
        return new self(self::TYPE_COMMENT, $id, comment: $comment);
    }

    // ======================================================
    // HELPERS
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

    /**
     * Retourne l'entité complète si elle a été injectée (utile dans les Factories).
     */
    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function getComment(): ?Comment
    {
        return $this->comment;
    }

    // ======================================================
    // SERIALISATION
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