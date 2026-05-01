<?php

declare(strict_types=1);

namespace App\Domain\Entity\Trait;

/**
 * Trait qui factorise la validation XOR pour les entités qui doivent cibler
 * exactement un Post OU un Commentaire (jamais les deux, jamais aucun).
 *
 * Utilisé par Report et ModerationActionLog.
 */
trait XorTargetTrait
{
    /**
     * Valide que l'entité cible exactement un seul contenu (Post XOR Comment).
     *
     * @throws \LogicException
     */
    public function assertExactlyOneTarget(): void
    {
        $hasPost    = $this->post !== null;
        $hasComment = $this->comment !== null;

        if ($hasPost === $hasComment) {
            throw new \LogicException(
                'Un signalement ou un log de modération doit cibler soit un post, soit un commentaire — pas les deux, pas aucun.'
            );
        }
    }
}