<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Contract\ModeratableContentInterface;
use App\Domain\Entity\Trait\ContentStatusBehavior;
use App\Domain\Enum\ContentStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entité Comment – Représente un commentaire sur un Post.
 *
 * Dans cette DDD Light, Comment est un Aggregate Root secondaire.
 * Il est toujours lié à un Post (relation obligatoire) et à un auteur.
 *
 * Responsabilités métier :
 * - Gérer son propre statut de visibilité / modération
 * - Incrémenter/décrémenter les compteurs de signalements
 * - Maintenir la synchronisation bidirectionnelle avec le Post parent
 * - Fournir des méthodes claires pour les services d’application
 *
 * Note : les annotations Doctrine restent ici pour simplifier la DDD Light.
 */
#[ORM\Entity(repositoryClass: \App\Infrastructure\Persistence\Repository\CommentRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(
    name: 'comment',
    indexes: [
        new ORM\Index(name: 'idx_comment_post',        columns: ['post_id']),
        new ORM\Index(name: 'idx_comment_author',      columns: ['author_id']),
        new ORM\Index(name: 'idx_comment_status',      columns: ['status']),
        new ORM\Index(name: 'idx_comment_created_at',  columns: ['created_at']),
        new ORM\Index(name: 'idx_comment_post_status', columns: ['post_id', 'status']),
    ]
)]
class Comment implements ModeratableContentInterface
{
    use ContentStatusBehavior;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Le commentaire ne peut pas être vide.')]
    #[Assert\Length(max: 2000, maxMessage: 'Le commentaire ne peut pas dépasser 2000 caractères.')]
    private string $content;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'report_count', options: ['default' => 0])]
    private int $reportCount = 0;

    #[ORM\ManyToOne(inversedBy: 'comments', fetch: 'EXTRA_LAZY')]
    #[ORM\JoinColumn(nullable: false)]
    private User $author;

    #[ORM\ManyToOne(inversedBy: 'comments', fetch: 'EXTRA_LAZY')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Post $post;

    /** @var Collection<int, Report> */
    #[ORM\OneToMany(mappedBy: 'comment', targetEntity: Report::class, fetch: 'EXTRA_LAZY')]
    private Collection $reports;

    /** @var Collection<int, ModerationActionLog> */
    #[ORM\OneToMany(mappedBy: 'comment', targetEntity: ModerationActionLog::class, fetch: 'EXTRA_LAZY')]
    private Collection $moderationLogs;

    public function __construct(User $author, Post $post, string $content)
    {
        $this->author = $author;
        $this->post = $post;
        $this->content = $content;
        $this->createdAt = new \DateTimeImmutable();

        $this->reports = new ArrayCollection();
        $this->moderationLogs = new ArrayCollection();

        $this->setStatus(ContentStatus::PUBLISHED);

        // Synchronisation bidirectionnelle
        $this->post->addComment($this);
    }

    // ======================================================
    // MÉTHODES MÉTIER
    // ======================================================

    public function updateContent(string $newContent): static
    {
        $this->content = $newContent;
        return $this;
    }

    public function incrementReportCount(int $by = 1): static
    {
        $this->reportCount += $by;
        return $this;
    }

    public function decrementReportCount(int $by = 1): static
    {
        $this->reportCount = max(0, $this->reportCount - $by);
        return $this;
    }

    public function deleteByAuthor(): static
    {
        $this->markAsDeleted();
        return $this;
    }

    // ======================================================
    // ModeratableContentInterface
    // ======================================================

    public function getTargetType(): string
    {
        return 'comment';
    }

    public function getPost(): Post
    {
        return $this->post;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

        public function markAsDeleted(): static
    {
        $this->status = ContentStatus::DELETED;
        $this->deletedAt = new \DateTimeImmutable();
        return $this;
    }

    // ======================================================
    // GETTERS
    // ======================================================

    public function getContent(): string
    {
        return $this->content;
    }
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
    public function getReportCount(): int
    {
        return $this->reportCount;
    }

    /** @return Collection<int, Report> */
    public function getReports(): Collection
    {
        return $this->reports;
    }

    /** @return Collection<int, ModerationActionLog> */
    public function getModerationLogs(): Collection
    {
        return $this->moderationLogs;
    }

    public function getExcerpt(int $length = 120): string
    {
        $text = strip_tags($this->content);
        return mb_strlen($text) > $length
            ? mb_substr($text, 0, $length) . '…'
            : $text;
    }

    public function getRelatedPostId(): int
    {
        return $this->getPost()->getId();
    }
}
