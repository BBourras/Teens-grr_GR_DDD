<?php

declare(strict_types=1);

namespace App\Ui\Security;

use App\Domain\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * UserChecker – Vérifications supplémentaires lors de l'authentification.
 *
 * Double couche de sécurité :
 * - checkPreAuth  → avant validation du mot de passe
 * - checkPostAuth → après validation du mot de passe
 */
final class UserChecker implements UserCheckerInterface
{
    /**
     * Vérifications avant authentification (mot de passe).
     */
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->isBanned()) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte a été suspendu. Contactez un administrateur si vous pensez qu’il s’agit d’une erreur.'
            );
        }
    }

    /**
     * Vérifications après authentification réussie.
     * (Pour futures évolutions : 2FA, expiration de mot de passe, etc.)
     */
    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        // Pour l'instant : rien de particulier
    }
}