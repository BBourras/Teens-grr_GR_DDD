<?php

declare(strict_types=1);

namespace App\Infrastructure\Factory;

use App\Domain\Entity\ModerationActionLog;
use App\Domain\Entity\User;
use App\Domain\Enum\ContentStatus;
use App\Domain\Enum\ModerationActionType;
use App\Domain\ValueObject\Target;

/**
 * Factory pour créer des logs de modération.
 *
 * Responsable de l'instanciation propre des ModerationActionLog
 * avec toutes les données nécessaires pour l'audit.
 */
final readonly class ModerationActionLogFactory
{
    /**
     * Crée un log de modération à partir d'un Target.
     *
     * @param Target           $target      Le contenu ciblé (Post ou Comment via ValueObject)
     * @param ModerationActionType $actionType Type d'action effectuée
     * @param ContentStatus    $oldStatus   Statut avant l'action
     * @param ContentStatus    $newStatus   Statut après l'action
     * @param User|null        $moderator   Modérateur (null = action automatique)
     * @param string|null      $reason      Raison / commentaire optionnel
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
        $log->setPreviousStatus($oldStatus);
        $log->setNewStatus($newStatus);
        $log->setReason($reason);
        $log->setModerator($moderator);

        // Assignation du target (Post OU Comment)
        if ($target->isPost()) {
            $log->assignPost($target->getPost());
        } else {
            $log->assignComment($target->getComment());
        }

        return $log;
    }
}