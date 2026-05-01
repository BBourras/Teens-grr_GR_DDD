<?php

declare(strict_types=1);

namespace App\Ui\Security\Voter;

use App\Domain\Entity\Post;
use App\Domain\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * Voter pour les droits sur les Posts.
 *
 * Attributs supportés :
 * - POST_VIEW
 * - POST_EDIT
 * - POST_DELETE
 *
 * Hiérarchie de décision :
 * 1. Utilisateur banni → refus immédiat
 * 2. Admin ou Modérateur → accès total
 * 3. Règles métier spécifiques (auteur + visibilité)
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

        // 1. Utilisateur banni → refus immédiat
        if ($user instanceof User && $user->isBanned()) {
            return false;
        }

        // 2. Admin ou Modérateur → accès total
        if (
            $this->security->isGranted('ROLE_ADMIN') ||
            $this->security->isGranted('ROLE_MODERATOR')
        ) {
            return true;
        }

        // 3. Règles métier selon l'attribut
        return match ($attribute) {
            self::VIEW   => $post->isVisible(),
            self::EDIT   => $this->canEdit($post, $user),
            self::DELETE => $this->canDelete($post, $user),
            default      => false,
        };
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