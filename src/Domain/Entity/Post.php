<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Contract\ModeratableContentInterface;
use App\Domain\Entity\Trait\ContentStatusBehavior;
use App\Domain\Enum\ContentStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entité Post – Article ironique sur les ados.
 *
 * Aggregate principal du domaine.
 * Toute modification de statut (masquage, suppression) doit passer par ModerationService.
 */
#[ORM\Entity(repositoryClass: \App\Infrastructure\Persistence\Repository\PostRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(
    name: 'post',
    indexes: [
        new ORM\Index(name: 'idx_post_status', columns: ['status']),
        new ORM\Index(name: 'idx_post_created_at', columns: ['created_at']),
        new ORM\Index(name: 'idx_post_status_created', columns: ['status', 'created_at']),
        new ORM\Index(name: 'idx_post_author', columns: ['author_id']),
        new ORM\Index(name: 'idx_post_reaction_score', columns: ['reaction_score']),
    ]
)]
class Post implements ModeratableContentInterface
{
    use ContentStatusBehavior;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'comment_count', options: ['default' => 0])]
    private int $commentCount = 0;

    #[ORM\Column(name: 'report_count', options: ['default' => 0])]
    private int $reportCount = 0;

    #[ORM\Column(name: 'reaction_score', options: ['default' => 0])]
    private int $reactionScore = 0;

    #[ORM\ManyToOne(inversedBy: 'posts', fetch: 'EXTRA_LAZY')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $author;

    /** @var Collection<int, Comment> */
    #[ORM\OneToMany(mappedBy: 'post', targetEntity: Comment::class, fetch: 'EXTRA_LAZY')]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $comments;

    /** @var Collection<int, Vote> */
    #[ORM\OneToMany(mappedBy: 'post', targetEntity: Vote::class, fetch: 'EXTRA_LAZY')]
    private Collection $votes;

    /** @var Collection<int, Report> */
    #[ORM\OneToMany(mappedBy: 'post', targetEntity: Report::class, fetch: 'EXTRA_LAZY')]
    private Collection $reports;

    /** @var Collection<int, ModerationActionLog> */
    #[ORM\OneToMany(mappedBy: 'post', targetEntity: ModerationActionLog::class, fetch: 'EXTRA_LAZY')]
    private Collection $moderationLogs;

    public function __construct(User $author, string $title, string $content)
    {
        $this->author = $author;
        $this->title = $title;
        $this->content = $content;
        $this->createdAt = new \DateTimeImmutable();

        $this->comments = new ArrayCollection();
        $this->votes = new ArrayCollection();
        $this->reports = new ArrayCollection();
        $this->moderationLogs = new ArrayCollection();

        // Statut par défaut métier
        $this->setStatus(ContentStatus::PUBLISHED);
    }

    // ======================================================
    // MÉTHODES MÉTIER
    // ======================================================

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            if ($comment->isVisible()) {
                $this->incrementCommentCount();
            }
        }
        return $this;
    }

    /**
     * Mise à jour métier du contenu du post.
     * À utiliser via PostService plutôt qu'en direct.
     */
    public function updateContent(string $title, string $content): self
    {
        $this->title = $title;
        $this->content = $content;
        return $this;
    }

    public function incrementCommentCount(int $by = 1): static
    {
        $this->commentCount += $by;
        return $this;
    }

    public function decrementCommentCount(int $by = 1): static
    {
        $this->commentCount = max(0, $this->commentCount - $by);
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

    public function incrementReactionScore(int $by = 1): static
    {
        $this->reactionScore += $by;
        return $this;
    }

    public function setReactionScore(int $score): static
    {
        $this->reactionScore = max(0, $score);
        return $this;
    }

    // ======================================================
    // ModeratableContentInterface
    // ======================================================

    public function getTargetType(): string
    {
        return 'post';
    }

    public function getPost(): static
    {
        return $this;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    // ======================================================
    // GETTERS
    // ======================================================

    public function getTitle(): string { return $this->title; }
    public function getContent(): string { return $this->content; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getCommentCount(): int { return $this->commentCount; }
    public function getReportCount(): int { return $this->reportCount; }
    public function getReactionScore(): int { return $this->reactionScore; }

    /** @return Collection<int, Comment> */
    public function getComments(): Collection { return $this->comments; }

    /** @return Collection<int, Vote> */
    public function getVotes(): Collection { return $this->votes; }

    /** @return Collection<int, Report> */
    public function getReports(): Collection { return $this->reports; }

    /** @return Collection<int, ModerationActionLog> */
    public function getModerationLogs(): Collection { return $this->moderationLogs; }

    public function getExcerpt(int $length = 200): string
    {
        $text = strip_tags($this->content);
        return mb_strlen($text) > $length
            ? mb_substr($text, 0, $length) . '…'
            : $text;
    }
}