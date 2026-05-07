<?php

declare(strict_types=1);

namespace App\Infrastructure\Factory;

use App\Domain\Entity\Report;
use App\Domain\Entity\User;
use App\Domain\Enum\ReportReason;
use App\Domain\ValueObject\Target;

/**
 * ReportFactory – Crée les entités Report de façon cohérente.
 */
final readonly class ReportFactory
{
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

        if ($target->isPost()) {
            $report->assignPost($target->getPost());
        } elseif ($target->isComment()) {
            $report->assignComment($target->getComment());
        } else {
            throw new \LogicException('Type de Target non supporté dans ReportFactory');
        }

        return $report;
    }
}