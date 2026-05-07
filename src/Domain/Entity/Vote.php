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
 * - Contrainte XOR stricte : soit user, soit guestKey
 */
#[ORM\Entity(repositoryClass: VoteRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(
    name: 'vote',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uniq_vote_user_post', columns: ['user_id', 'post_id']),
        new ORM\UniqueConstraint(name: 'uniq_vote_guest_post', columns: ['guest_key', 'post_id']),
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

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'votes', fetch: 'EXTRA_LAZY')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: 'votes', fetch: 'EXTRA_LAZY')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Post $post;

    // ======================================================
    // DONNÉES MÉTIER
    // ======================================================

    #[ORM\Column(enumType: VoteType::class)]
    private VoteType $type;

    #[ORM\Column(name: 'guest_key', length: 64, nullable: true)]
    private ?string $guestKey = null;

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
    // CONSTRUCTEUR
    // ======================================================

    public function __construct(Post $post, VoteType $type)
    {
        $this->post = $post;
        $this->type = $type;           // ← Important : on force le type ici
        $this->createdAt = new \DateTimeImmutable();
    }

    // ======================================================
    // LIFECYCLE
    // ======================================================

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->assertValidVoter();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ======================================================
    // VALIDATION
    // ======================================================

    public function assertValidVoter(): void
    {
        $hasUser = $this->user !== null;
        $hasGuest = $this->guestKey !== null && $this->guestKey !== '';

        if ($hasUser === $hasGuest) {
            throw new \LogicException('Un vote doit avoir soit un utilisateur, soit une clé invité.');
        }
    }

    // ======================================================
    // MÉTHODES MÉTIER
    // ======================================================

    public function assignUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function assignGuest(string $guestKey, ?string $guestIpHash = null): static
    {
        $this->guestKey = $guestKey;
        if ($guestIpHash !== null) {
            $this->guestIpHash = $guestIpHash;
        }
        return $this;
    }

    public function changeType(VoteType $newType): static
    {
        $this->type = $newType;
        return $this;
    }

    // ======================================================
    // GETTERS
    // ======================================================

    public function getId(): ?int { return $this->id; }
    public function getPost(): Post { return $this->post; }
    public function getType(): VoteType { return $this->type; }
    public function getUser(): ?User { return $this->user; }
    public function getGuestKey(): ?string { return $this->guestKey; }
    public function getGuestIpHash(): ?string { return $this->guestIpHash; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
}