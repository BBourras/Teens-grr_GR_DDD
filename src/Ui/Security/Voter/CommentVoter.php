<?php

declare(strict_types=1);

namespace App\Ui\Security\Voter;

use App\Domain\Entity\Comment;
use App\Domain\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * Voter pour les droits sur les Commentaires.
 *
 * Attributs supportés :
 * - COMMENT_VIEW
 * - COMMENT_EDIT
 * - COMMENT_DELETE
 *
 * Hiérarchie de décision :
 * 1. Utilisateur banni → refus immédiat
 * 2. Admin ou Modérateur → accès total
 * 3. Règles métier (auteur + visibilité)
 */
final class CommentVoter extends Voter
{
    public const VIEW   = 'COMMENT_VIEW';
    public const EDIT   = 'COMMENT_EDIT';
    public const DELETE = 'COMMENT_DELETE';

    public function __construct(
        private readonly Security $security
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true)
            && $subject instanceof Comment;
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null
    ): bool {
        /** @var User|null $user */
        $user = $token->getUser();

        /** @var Comment $comment */
        $comment = $subject;

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
            self::VIEW   => $comment->isVisible(),
            self::EDIT   => $this->canEdit($comment, $user),
            self::DELETE => $this->canDelete($comment, $user),
            default      => false,
        };
    }

    private function canEdit(Comment $comment, ?User $user): bool
    {
        return $user instanceof User
            && $comment->getAuthor() === $user
            && !$comment->isDeleted();
    }

    private function canDelete(Comment $comment, ?User $user): bool
    {
        return $user instanceof User
            && $comment->getAuthor() === $user
            && !$comment->isDeleted();
    }
}