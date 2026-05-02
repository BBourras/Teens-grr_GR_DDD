<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Contract\XorTargetInterface;
use App\Domain\Entity\Trait\XorTargetTrait;
use App\Domain\Enum\ContentStatus;
use App\Domain\Enum\ModerationActionType;
use App\Infrastructure\Persistence\Repository\ModerationActionLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Journal immuable des actions de modération.
 *
 * Cet enregistrement permet de tracer chaque modification de statut
 * sur un Post ou un Commentaire (masquage automatique, action manuelle,
 * suppression par auteur, restauration, etc.).
 * Très utile en cas de litige ou d'audit.
 */
#[ORM\Entity(repositoryClass: ModerationActionLogRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(
    name: 'moderation_action_log',
    indexes: [
        new ORM\Index(name: 'idx_modlog_action_created', columns: ['action_type', 'created_at']),
        new ORM\Index(name: 'idx_modlog_post',           columns: ['post_id']),
        new ORM\Index(name: 'idx_modlog_comment',        columns: ['comment_id']),
        new ORM\Index(name: 'idx_modlog_moderator',      columns: ['moderator_id']),
    ]
)]
class ModerationActionLog implements XorTargetInterface
{
    use XorTargetTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // ======================================================
    // DONNÉES D'AUDIT
    // ======================================================

    #[ORM\Column(name: 'action_type', enumType: ModerationActionType::class)]
    private ModerationActionType $actionType;

    #[ORM\Column(name: 'previous_status', length: 30, nullable: true)]
    private ?string $previousStatus = null;

    #[ORM\Column(name: 'new_status', length: 30, nullable: true)]
    private ?string $newStatus = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $context = null;

    // ======================================================
    // DATE
    // ======================================================

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    // ======================================================
    // RELATIONS
    // ======================================================

    /**
     * Modérateur ayant effectué l'action (null pour les actions automatiques).
     */
    #[ORM\ManyToOne(fetch: 'EXTRA_LAZY')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $moderator = null;

    #[ORM\ManyToOne(inversedBy: 'moderationLogs', fetch: 'EXTRA_LAZY')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Post $post = null;

    #[ORM\ManyToOne(inversedBy: 'moderationLogs', fetch: 'EXTRA_LAZY')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Comment $comment = null;

    // ======================================================
    // LIFECYCLE
    // ======================================================

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt ??= new \DateTimeImmutable();
        $this->assertExactlyOneTarget();
    }

    // ======================================================
    // MÉTHODES MÉTIER
    // ======================================================

    /**
     * Assigne un Post et nettoie le Comment (XOR).
     */
    public function assignPost(Post $post): static
    {
        $this->post = $post;
        $this->comment = null;
        return $this;
    }

    /**
     * Assigne un Comment et nettoie le Post (XOR).
     */
    public function assignComment(Comment $comment): static
    {
        $this->comment = $comment;
        $this->post = null;
        return $this;
    }

    public function setPreviousStatus(ContentStatus $status): static
    {
        $this->previousStatus = $status->value;
        return $this;
    }

    public function setNewStatus(ContentStatus $status): static
    {
        $this->newStatus = $status->value;
        return $this;
    }

    public function isForPost(): bool
    {
        return $this->post !== null;
    }

    public function isForComment(): bool
    {
        return $this->comment !== null;
    }

    public function isAutomaticAction(): bool
    {
        return $this->moderator === null;
    }

    // ======================================================
    // GETTERS & SETTERS
    // ======================================================

    public function getId(): ?int 
    { 
        return $this->id; 
    }

    public function getActionType(): ModerationActionType 
    { 
        return $this->actionType; 
    }

    public function setActionType(ModerationActionType $actionType): static 
    { 
        $this->actionType = $actionType; 
        return $this; 
    }

    public function getPreviousStatus(): ?string 
    { 
        return $this->previousStatus; 
    }

    public function getNewStatus(): ?string 
    { 
        return $this->newStatus; 
    }

    public function getReason(): ?string 
    { 
        return $this->reason; 
    }

    public function setReason(?string $reason): static 
    { 
        $this->reason = $reason; 
        return $this; 
    }

    public function getContext(): ?array 
    { 
        return $this->context; 
    }

    public function setContext(?array $context): static 
    { 
        $this->context = $context; 
        return $this; 
    }

    public function getCreatedAt(): \DateTimeImmutable 
    { 
        return $this->createdAt; 
    }

    public function getModerator(): ?User 
    { 
        return $this->moderator; 
    }

    public function setModerator(?User $moderator): static 
    { 
        $this->moderator = $moderator; 
        return $this; 
    }

    public function getPost(): ?Post 
    { 
        return $this->post; 
    }

    public function getComment(): ?Comment 
    { 
        return $this->comment; 
    }
}