<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Contract\ModeratableContentInterface;
use App\Domain\Contract\ModerationServiceInterface;
use App\Domain\Contract\ReportRepositoryInterface;
use App\Domain\Entity\Comment;
use App\Domain\Entity\Post;
use App\Domain\Entity\User;
use App\Domain\Enum\ContentStatus;
use App\Domain\Enum\ReportReason;
use App\Domain\Event\ContentStatusChangedEvent; // si on veut émettre un événement
use App\Factory\ReportFactory;
use App\ValueObject\Target;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * ReportService – Gestion des signalements et de l’auto-masquage.
 *
 * Responsabilités :
 * - Création d’un signalement (Post ou Comment)
 * - Protection contre les doubles signalements (unicité DB)
 * - Logique d’auto-modération basée sur seuil + ratio
 * - Délégation totale de l’action de masquage à ModerationService
 */
final class ReportService
{
    private const MIN_REPORT_THRESHOLD = 5;
    private const RATIO_THRESHOLD      = 0.4;   // reports / score

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReportRepositoryInterface $reportRepository,
        private readonly ModerationServiceInterface $moderationService,
        private readonly EventDispatcherInterface $eventDispatcher,   // optionnel mais recommandé
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

            $target = Target::fromContent($content);

            $report = ReportFactory::create(
                $target,
                $user,
                $reason,
                $reasonDetail
            );

            // Protection contre double signalement
            try {
                $this->em->persist($report);
                $this->em->flush();
            } catch (UniqueConstraintViolationException) {
                // L’utilisateur a déjà signalé ce contenu → on sort silencieusement
                return;
            }

            // Mise à jour du compteur dénormalisé
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

        // Score de popularité (pour pondérer les signalements)
        $score = $content instanceof Post
            ? max(1, $content->getReactionScore())
            : 1;

        $ratio = $reportCount / $score;

        if ($reportCount >= self::MIN_REPORT_THRESHOLD && $ratio >= self::RATIO_THRESHOLD) {
            $this->moderationService->autoHide($content);

            // Optionnel : émettre un événement pour notifier ou logger
            $this->eventDispatcher->dispatch(
                new ContentStatusChangedEvent(
                    $content,
                    $content->getStatusEnum(), // ancien statut
                    ContentStatus::AUTO_HIDDEN,
                    null,
                    null,
                    'Auto-masquage après signalements'
                )
            );
        }
    }
}