<?php

declare(strict_types=1);

namespace App\Infrastructure\Factory;

use App\Domain\Entity\ModerationActionLog;
use App\Domain\Entity\User;
use App\Domain\Enum\ContentStatus;
use App\Domain\Enum\ModerationActionType;
use App\Domain\ValueObject\Target;

/**
 * ModerationActionLogFactory – Crée les logs d'audit de modération.
 *
 * Centralise la création des logs pour garantir cohérence et traçabilité.
 */
final readonly class ModerationActionLogFactory
{
    /**
     * Crée un log complet d'action de modération.
     */
    public function create(
        Target $target,
        ModerationActionType $actionType,
        ContentStatus $oldStatus,
        ContentStatus $newStatus,
        ?User $moderator = null,
        ?string $reason = null
    ): ModerationActionLog {
        $log = new ModerationActionLog();

        $log->setActionType($actionType);
        $log->setPreviousStatus($oldStatus);     // Passage de l'enum complet
        $log->setNewStatus($newStatus);          // Passage de l'enum complet
        $log->setReason($reason);
        $log->setModerator($moderator);

        // Assignation du target (Post OU Comment)
        if ($target->isPost()) {
            $log->assignPost($target->getPost());
        } elseif ($target->isComment()) {
            $log->assignComment($target->getComment());
        } else {
            throw new \LogicException('Type de Target non supporté dans ModerationActionLogFactory');
        }

        return $log;
    }
}