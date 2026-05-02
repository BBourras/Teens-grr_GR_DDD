<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Contract\ModeratableContentInterface;
use App\Domain\Contract\ModerationServiceInterface;
use App\Domain\Contract\ReportRepositoryInterface;
use App\Domain\Entity\Comment;
use App\Domain\Entity\Post;
use App\Domain\Entity\User;
use App\Domain\Enum\ReportReason;
use App\Domain\ValueObject\Target;
use App\Infrastructure\Factory\ReportFactory;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * ReportService – Gestion des signalements et de l’auto-masquage.
 *
 * Responsabilités principales :
 * - Création sécurisée d’un signalement (Post ou Comment)
 * - Protection contre les doubles signalements
 * - Logique d’auto-modération basée sur seuil et ratio
 * - Délégation totale des actions de masquage à ModerationService
 */
final class ReportService
{
    private const MIN_REPORT_THRESHOLD = 5;
    private const RATIO_THRESHOLD      = 0.4;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReportRepositoryInterface $reportRepository,
        private readonly ModerationServiceInterface $moderationService,
    ) {}

    // ======================================================
    // API PUBLIQUE
    // ======================================================

    public function reportPost(
        Post $post,
        User $user,
        ReportReason $reason,
        ?string $reasonDetail = null
    ): void {
        $this->report($post, $user, $reason, $reasonDetail);
    }

    public function reportComment(
        Comment $comment,
        User $user,
        ReportReason $reason,
        ?string $reasonDetail = null
    ): void {
        $this->report($comment, $user, $reason, $reasonDetail);
    }

    // ======================================================
    // LOGIQUE CENTRALE
    // ======================================================

    private function report(
        ModeratableContentInterface $content,
        User $user,
        ReportReason $reason,
        ?string $reasonDetail
    ): void {
        $this->em->wrapInTransaction(function () use ($content, $user, $reason, $reasonDetail) {

            // Création du signalement via Target
            $report = ReportFactory::create(
                Target::fromContent($content),
                $user,
                $reason,
                $reasonDetail
            );

            // Protection contre double signalement
            try {
                $this->em->persist($report);
                $this->em->flush();
            } catch (UniqueConstraintViolationException) {
                return; // Déjà signalé par cet utilisateur
            }

            // Mise à jour du compteur
            $content->incrementReportCount();

            // Auto-modération si nécessaire
            $this->handleAutoModeration($content);
        });
    }

    // ======================================================
    // AUTO-MODÉRATION
    // ======================================================

    private function handleAutoModeration(ModeratableContentInterface $content): void
    {
        if (!$content->isVisible()) {
            return;
        }

        $reportCount = $content->getReportCount();

        $score = $content instanceof Post 
            ? max(1, $content->getReactionScore()) 
            : 1;

        $ratio = $reportCount / $score;

        if ($reportCount >= self::MIN_REPORT_THRESHOLD && $ratio >= self::RATIO_THRESHOLD) {
            $this->moderationService->autoHide($content);
        }
    }

    // ======================================================
    // MÉTHODES DE LECTURE
    // ======================================================

    public function hasAlreadyReportedPost(Post $post, User $user): bool
    {
        return $this->reportRepository->hasAlreadyReportedPost($post, $user);
    }

    public function hasAlreadyReportedComment(Comment $comment, User $user): bool
    {
        return $this->reportRepository->hasAlreadyReportedComment($comment, $user);
    }

    public function getPendingReports(int $limit = 50): array
    {
        return $this->reportRepository->findPendingReports($limit);
    }
}