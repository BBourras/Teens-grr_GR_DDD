<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Enum\VoteType;
use App\Infrastructure\Persistence\Repository\VoteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entité représentant un vote/réaction (emoji) sur un Post.
 *
 * Règles métier importantes :
 * - 1 vote maximum par utilisateur connecté et par post
 * - 1 vote maximum par invité (via guestKey) et par post
 * - Un vote peut être modifié (changement d'emoji)
 * - Contrainte XOR stricte : soit user, soit guestKey (jamais les deux, jamais aucun)
 */
#[ORM\Entity(repositoryClass: VoteRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(
    name: 'vote',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uniq_vote_user_post', columns: ['user_id', 'post_id']),
        new ORM\UniqueConstraint(name: 'uniq_vote_guest_post', columns: ['guest_key', 'post_id']),
    ],
    indexes: [
        new ORM\Index(name: 'idx_vote_post',              columns: ['post_id']),
        new ORM\Index(name: 'idx_vote_post_type',         columns: ['post_id', 'type']),
        new ORM\Index(name: 'idx_vote_created_at',        columns: ['created_at']),
        new ORM\Index(name: 'idx_vote_guest_key',         columns: ['guest_key']),
        new ORM\Index(name: 'idx_vote_guest_post_created', columns: ['guest_key', 'post_id', 'created_at']),
    ]
)]
class Vote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // ======================================================
    // RELATIONS
    // ======================================================

    /**
     * Utilisateur connecté (null pour les votes invités).
     */
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'votes', fetch: 'EXTRA_LAZY')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $user = null;

    /**
     * Post concerné par ce vote.
     */
    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: 'votes', fetch: 'EXTRA_LAZY')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Post $post;

    // ======================================================
    // DONNÉES MÉTIER
    // ======================================================

    /**
     * Type de réaction choisie.
     */
    #[ORM\Column(enumType: VoteType::class)]
    private VoteType $type;

    /**
     * Identifiant invité (UUID généralement stocké en cookie).
     * Obligatoire pour les votes non connectés.
     */
    #[ORM\Column(name: 'guest_key', length: 64, nullable: true)]
    private ?string $guestKey = null;

    /**
     * Hash anonymisé de l'IP (SHA-256) pour limitation anti-abus.
     */
    #[ORM\Column(name: 'guest_ip_hash', length: 64, nullable: true)]
    private ?string $guestIpHash = null;

    // ======================================================
    // DATES
    // ======================================================

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    // ======================================================
    // LIFECYCLE
    // ======================================================

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt ??= new \DateTimeImmutable();
        $this->assertValidVoter();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ======================================================
    // VALIDATION MÉTIER
    // ======================================================

    /**
     * Garantit la règle XOR : soit un utilisateur connecté, soit un invité.
     *
     * @throws \LogicException
     */
    public function assertValidVoter(): void
    {
        $hasUser = $this->user !== null;
        $hasGuest = $this->guestKey !== null && $this->guestKey !== '';

        if ($hasUser === $hasGuest) {
            throw new \LogicException(
                'Un vote doit avoir soit un utilisateur connecté, soit une clé invité — pas les deux, pas aucun.'
            );
        }
    }

    // ======================================================
    // MÉTHODES MÉTIER
    // ======================================================

    public function assignUser(User $user): static
    {
        $this->setUser($user);
        return $this;
    }

    public function assignGuest(string $guestKey, ?string $guestIpHash = null): static
    {
        $this->setGuestKey($guestKey);
        if ($guestIpHash !== null) {
            $this->setGuestIpHash($guestIpHash);
        }
        return $this;
    }

    public function assignPost(Post $post): static
    {
        $this->setPost($post);
        return $this;
    }

    public function changeType(VoteType $newType): static
    {
        $this->type = $newType;
        return $this;
    }

    // ======================================================
    // HELPERS
    // ======================================================

    public function isUserVote(): bool
    {
        return $this->user !== null;
    }

    public function isGuestVote(): bool
    {
        return $this->user === null;
    }

    // ======================================================
    // GETTERS & SETTERS PROTÉGÉS
    // ======================================================

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    protected function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getPost(): Post { return $this->post; }
    protected function setPost(Post $post): static { $this->post = $post; return $this; }

    public function getType(): VoteType { return $this->type; }
    public function setType(VoteType $type): static { $this->type = $type; return $this; }

    public function getGuestKey(): ?string { return $this->guestKey; }
    protected function setGuestKey(?string $guestKey): static { $this->guestKey = $guestKey; return $this; }

    public function getGuestIpHash(): ?string { return $this->guestIpHash; }
    protected function setGuestIpHash(?string $guestIpHash): static { $this->guestIpHash = $guestIpHash; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
}