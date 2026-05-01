<?php

declare(strict_types=1);

namespace App\Infrastructure\Factory;

use App\Domain\Entity\Report;
use App\Domain\Entity\User;
use App\Domain\Enum\ReportReason;
use App\Domain\ValueObject\Target;

final class ReportFactory
{
    public static function create(
        Target $target,
        User $user,
        ReportReason $reason,
        ?string $detail = null
    ): Report {
        $report = (new Report())
            ->setUser($user)
            ->setReason($reason)
            ->setReasonDetail($detail);

        if ($target->isPost()) {
            $report->assignPost($target->getPost());
        } else {
            $report->assignComment($target->getComment());
        }

        return $report;
    }
}