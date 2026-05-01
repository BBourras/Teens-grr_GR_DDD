<?php

declare(strict_types=1);

namespace App\Ui\Security;

use App\Domain\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Vérifications de l'état du compte lors de l'authentification.
 *
 * Double protection avec les Voters :
 * - UserChecker bloque à la CONNEXION (checkPreAuth)
 * - Voters bloquent à l'ACTION
 */
class UserChecker implements UserCheckerInterface
{
    /**
     * Vérifie l'état du compte AVANT la validation du mot de passe.
     */
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (in_array('ROLE_BANNED', $user->getRoles(), true)) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte a été suspendu. Contactez un administrateur.'
            );
        }
    }

    /**
     * Vérifie l'état du compte APRÈS la validation du mot de passe.
     */
    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        // Cas futurs : 2FA, expiration password, etc.
    }
}