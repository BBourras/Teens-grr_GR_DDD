<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Contract\XorTargetInterface;
use App\Domain\Entity\Trait\XorTargetTrait;
use App\Domain\Enum\ReportReason;
use App\Infrastructure\Persistence\Repository\ReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entité représentant un signalement (post OU commentaire).
 *
 * - La contrainte XOR est gérée par ModerationXorListener + assertExactlyOneTarget()
 * - Un utilisateur ne peut signaler qu'une fois le même contenu
 * - La raison est obligatoire
 */
#[ORM\Entity(repositoryClass: ReportRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(
    name: 'report',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uniq_user_post_report',    columns: ['user_id', 'post_id']),
        new ORM\UniqueConstraint(name: 'uniq_user_comment_report', columns: ['user_id', 'comment_id']),
    ],
    indexes: [
        new ORM\Index(name: 'idx_report_post',       columns: ['post_id']),
        new ORM\Index(name: 'idx_report_comment',    columns: ['comment_id']),
        new ORM\Index(name: 'idx_report_user',       columns: ['user_id']),
        new ORM\Index(name: 'idx_report_created_at', columns: ['created_at']),
        new ORM\Index(name: 'idx_report_reason',     columns: ['reason']),
    ]
)]
class Report implements XorTargetInterface
{
    use XorTargetTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // ======================================================
    // DONNÉES MÉTIER
    // ======================================================

    #[ORM\Column(enumType: ReportReason::class)]
    private ReportReason $reason;

    #[ORM\Column(name: 'reason_detail', type: Types::STRING, length: 500, nullable: true)]
    private ?string $reasonDetail = null;

    // ======================================================
    // DATES
    // ======================================================

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    // ======================================================
    // RELATIONS
    // ======================================================

    #[ORM\ManyToOne(inversedBy: 'reports', fetch: 'EXTRA_LAZY')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(inversedBy: 'reports', fetch: 'EXTRA_LAZY')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Post $post = null;

    #[ORM\ManyToOne(inversedBy: 'reports', fetch: 'EXTRA_LAZY')]
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

    public function assignPost(Post $post): static
    {
        $this->post = $post;
        $this->comment = null;
        return $this;
    }

    public function assignComment(Comment $comment): static
    {
        $this->comment = $comment;
        $this->post = null;
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

    // ======================================================
    // GETTERS & SETTERS
    // ======================================================

    public function getId(): ?int { return $this->id; }

    public function getReason(): ReportReason { return $this->reason; }
    public function setReason(ReportReason $reason): static 
    { 
        $this->reason = $reason; 
        return $this; 
    }

    public function getReasonDetail(): ?string { return $this->reasonDetail; }
    public function setReasonDetail(?string $reasonDetail): static 
    { 
        $this->reasonDetail = $reasonDetail; 
        return $this; 
    }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getUser(): User { return $this->user; }
    public function setUser(User $user): static 
    { 
        $this->user = $user; 
        return $this; 
    }

    public function getPost(): ?Post { return $this->post; }
    public function getComment(): ?Comment { return $this->comment; }
}