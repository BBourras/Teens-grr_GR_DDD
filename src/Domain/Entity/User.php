<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entité User – Utilisateur de la plateforme.
 *
 * Responsabilités :
 * - Gestion du compte (email, username, password)
 * - Rôles et permissions (USER, MODERATOR, ADMIN, BANNED)
 * - Relations avec ses contenus (posts, commentaires, votes, signalements)
 */
#[ORM\Entity(repositoryClass: \App\Infrastructure\Persistence\Repository\UserRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(
    name: 'user',
    indexes: [
        new ORM\Index(name: 'idx_user_created_at', columns: ['created_at']),
    ]
)]
#[UniqueEntity(fields: ['email'],    message: 'Cet email est déjà utilisé.')]
#[UniqueEntity(fields: ['username'], message: 'Ce pseudo est déjà pris.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private string $email;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 50)]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9_\-]+$/',
        message: 'Le pseudo ne peut contenir que des lettres, chiffres, tirets et underscores.',
    )]
    private string $username;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private string $password;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Post> */
    #[ORM\OneToMany(mappedBy: 'author', targetEntity: Post::class, fetch: 'EXTRA_LAZY')]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $posts;

    /** @var Collection<int, Comment> */
    #[ORM\OneToMany(mappedBy: 'author', targetEntity: Comment::class, fetch: 'EXTRA_LAZY')]
    private Collection $comments;

    /** @var Collection<int, Vote> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Vote::class, fetch: 'EXTRA_LAZY')]
    private Collection $votes;

    /** @var Collection<int, Report> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Report::class, fetch: 'EXTRA_LAZY')]
    private Collection $reports;

    public function __construct()
    {
        $this->posts    = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->votes    = new ArrayCollection();
        $this->reports  = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt ??= new \DateTimeImmutable();
    }

    // ======================================================
    // UserInterface
    // ======================================================

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    public function setRoles(array $roles): self
    {
        $this->roles = array_values(array_unique($roles));
        return $this;
    }

    public function eraseCredentials(): void
    {
        // Pas de données sensibles temporaires pour l'instant
    }

    // ======================================================
    // MÉTHODES MÉTIER
    // ======================================================

    public function isBanned(): bool
    {
        return in_array('ROLE_BANNED', $this->roles, true);
    }

    public function isModerator(): bool
    {
        return $this->isAdmin() || in_array('ROLE_MODERATOR', $this->roles, true);
    }

    public function isAdmin(): bool
    {
        return in_array('ROLE_ADMIN', $this->roles, true);
    }

    // ======================================================
    // GETTERS & SETTERS
    // ======================================================

    public function getId(): ?int { return $this->id; }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self
    {
        $this->email = strtolower(trim($email));
        return $this;
    }

    public function getUsername(): string { return $this->username; }
    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @return Collection<int, Post> */
    public function getPosts(): Collection { return $this->posts; }

    /** @return Collection<int, Comment> */
    public function getComments(): Collection { return $this->comments; }

    /** @return Collection<int, Vote> */
    public function getVotes(): Collection { return $this->votes; }

    /** @return Collection<int, Report> */
    public function getReports(): Collection { return $this->reports; }
}