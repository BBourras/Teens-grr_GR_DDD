<?php

declare(strict_types=1);

namespace App\Infrastructure\Factory;

use App\Domain\Entity\Report;
use App\Domain\Entity\User;
use App\Domain\Enum\ReportReason;
use App\Domain\ValueObject\Target;

/**
 * Factory responsable de la création des entités Report.
 *
 * Centralise la logique de construction pour garantir la cohérence
 * et simplifier les services.
 */
final readonly class ReportFactory
{
    /**
     * Crée un Report à partir d'une Target.
     */
    public static function create(
        Target $target,
        User $user,
        ReportReason $reason,
        ?string $reasonDetail = null
    ): Report {
        $report = new Report();

        $report->setUser($user);
        $report->setReason($reason);
        $report->setReasonDetail($reasonDetail);

        // Assignation selon le type de Target
        if ($target->isPost()) {
            $report->assignPost($target->getPost() ?? throw new \LogicException('Post entity missing in Target'));
        } elseif ($target->isComment()) {
            $report->assignComment($target->getComment() ?? throw new \LogicException('Comment entity missing in Target'));
        } else {
            throw new \LogicException('Unsupported target type in ReportFactory');
        }

        return $report;
    }
}