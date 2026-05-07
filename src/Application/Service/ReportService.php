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
 * ReportService – Gestion des signalements et de l’auto-modération.
 *
 * Responsabilités :
 * - Création sécurisée d’un signalement (Post ou Comment)
 * - Protection contre les doubles signalements
 * - Logique d’auto-modération (seuil + ratio)
 * - Délégation totale des actions de masquage/restauration à ModerationService
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

            // Création du signalement via Target (polymorphisme propre)
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
                return; // L'utilisateur a déjà signalé ce contenu
            }

            // Mise à jour du compteur dénormalisé
            $content->incrementReportCount();

            // Vérification de l’auto-modération
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
        if ($reportCount < self::MIN_REPORT_THRESHOLD) {
            return;
        }

        // Score de base (pour les posts) ou 1 (pour les commentaires)
        $score = $content instanceof Post 
            ? max(1, $content->getReactionScore()) 
            : 1;

        $ratio = $reportCount / $score;

        if ($ratio >= self::RATIO_THRESHOLD) {
            $this->moderationService->autoHide($content);
        }
    }

    // ======================================================
    // QUERIES (Read)
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