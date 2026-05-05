<?php

declare(strict_types=1);

namespace App\Ui\Security\Voter;

use App\Domain\Entity\Post;
use App\Domain\Entity\User;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter pour les droits sur les Posts.
 */
final class PostVoter extends Voter
{
    public const VIEW   = 'POST_VIEW';
    public const EDIT   = 'POST_EDIT';
    public const DELETE = 'POST_DELETE';

    public function __construct(
        private readonly Security $security
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true)
            && $subject instanceof Post;
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null
    ): bool {
        /** @var User|null $user */
        $user = $token->getUser();

        /** @var Post $post */
        $post = $subject;

        // Utilisateur banni → refus immédiat
        if ($user instanceof User && $user->isBanned()) {
            return false;
        }

        // Admin ou Modérateur → accès total
        if ($this->security->isGranted('ROLE_ADMIN') || $this->security->isGranted('ROLE_MODERATOR')) {
            return true;
        }

        // Règles métier
        return match ($attribute) {
            self::VIEW   => $this->canView($post, $user),
            self::EDIT   => $this->canEdit($post, $user),
            self::DELETE => $this->canDelete($post, $user),
            default      => false,
        };
    }

    private function canView(Post $post, ?User $user): bool
    {
        if ($post->isVisible()) {
            return true;
        }

        return $user instanceof User && $post->getAuthor() === $user;
    }

    private function canEdit(Post $post, ?User $user): bool
    {
        return $user instanceof User
            && $post->getAuthor() === $user
            && !$post->isDeleted();
    }

    private function canDelete(Post $post, ?User $user): bool
    {
        return $user instanceof User
            && $post->getAuthor() === $user
            && !$post->isDeleted();
    }
}
