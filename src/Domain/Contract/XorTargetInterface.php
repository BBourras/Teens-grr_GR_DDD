<?php

declare(strict_types=1);

namespace App\Domain\Contract;

/**
 * Interface pour les entités qui doivent cibler exactement UN Post OU UN Comment
 * (contrainte XOR stricte).
 *
 * Implémentée par Report et ModerationActionLog.
 */
interface XorTargetInterface
{
    /**
     * Vérifie que l'entité cible exactement un Post ou un Comment.
     *
     * @throws \LogicException si la règle XOR est violée.
     */
    public function assertExactlyOneTarget(): void;
}