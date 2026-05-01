<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Contract\ModeratableContentInterface;
use App\Domain\Contract\ModerationServiceInterface;
use App\Domain\Entity\User;
use App\Domain\Enum\ContentStatus;
use App\Domain\Enum\ModerationActionType;
use App\Domain\Event\ContentStatusChangedEvent;
use App\Infrastructure\Factory\ModerationActionLogFactory;
use App\Infrastructure\Persistence\Repository\ModerationActionLogRepository; // si besoin
use App\Domain\ValueObject\Target;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;


/**
 * Service central de modération – Version DDD Light stabilisée.
 *
 * Responsable de toutes les transitions de statut sur Post et Comment,
 * avec logging systématique et transitions sécurisées.
 */
final class ModerationService implements ModerationServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ModerationActionLogFactory $logFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function hideByModerator(
        ModeratableContentInterface $content,
        User $moderator,
        ?string $reason = null
    ): void {
        $this->transition(
            $content,
            ContentStatus::HIDDEN_BY_MODERATOR,
            ModerationActionType::MODERATOR_HIDE,
            $moderator,
            $reason
        );
    }

    public function autoHide(ModeratableContentInterface $content): void
    {
        $this->transition(
            $content,
            ContentStatus::AUTO_HIDDEN,
            ModerationActionType::AUTO_HIDE,
            null,
            'Seuil de 5 signalements atteint'
        );
    }

    public function deleteByModerator(
        ModeratableContentInterface $content,
        User $moderator,
        ?string $reason = null
    ): void {
        $this->transition(
            $content,
            ContentStatus::DELETED,
            ModerationActionType::MODERATOR_DELETE,
            $moderator,
            $reason
        );
    }

    public function deleteByAuthor(
        ModeratableContentInterface $content,
        User $author
    ): void {
        $this->transition(
            $content,
            ContentStatus::DELETED,
            ModerationActionType::AUTHOR_DELETE,
            $author,
            'Suppression par l’auteur lui-même'
        );
    }

    public function restore(
        ModeratableContentInterface $content,
        ?User $moderator = null,
        ?string $reason = null
    ): void {
        $this->transition(
            $content,
            ContentStatus::PUBLISHED,
            ModerationActionType::RESTORE,
            $moderator,
            $reason ?? 'Restauration manuelle'
        );
    }

    public function confirmAutoHide(
        ModeratableContentInterface $content,
        User $moderator,
        ?string $reason = null
    ): void {
        $this->transition(
            $content,
            ContentStatus::HIDDEN_BY_MODERATOR,
            ModerationActionType::REPORTS_CONFIRMED,
            $moderator,
            $reason ?? 'Signalements confirmés par modérateur'
        );
    }

    public function rejectReport(
        ModeratableContentInterface $content,
        User $moderator,
        ?string $reason = null
    ): void {
        $this->transition(
            $content,
            ContentStatus::PUBLISHED,
            ModerationActionType::REPORTS_REJECTED,
            $moderator,
            $reason ?? 'Signalements rejetés'
        );
    }

    // ======================================================
    // CŒUR : MACHINE À ÉTATS + TRANSACTION
    // ======================================================

    private function transition(
        ModeratableContentInterface $content,
        ContentStatus $newStatus,
        ModerationActionType $actionType,
        ?User $actor,
        ?string $reason
    ): void {
        $this->em->wrapInTransaction(function () use ($content, $newStatus, $actionType, $actor, $reason) {

            $oldStatus = $content->getStatusEnum();

            if (!$this->isTransitionAllowed($oldStatus, $newStatus)) {
                throw new \LogicException(sprintf(
                    'Transition de statut interdite : %s → %s',
                    $oldStatus->value,
                    $newStatus->value
                ));
            }

            // Application du nouvel état
            $content->setStatus($newStatus);

            if ($newStatus === ContentStatus::DELETED) {
                $content->setDeletedAt(new \DateTimeImmutable());
            } elseif ($newStatus === ContentStatus::PUBLISHED) {
                $content->setDeletedAt(null);
            }

            // Création et persistance du log
            $target = Target::fromContent($content);

            $log = $this->logFactory->create(
                $target,
                $actionType,
                $oldStatus,
                $newStatus,
                $actor,
                $reason
            );

            $this->em->persist($log);

            // Événement domaine (pour listeners, notifications, etc.)
            $event = new ContentStatusChangedEvent($content, $oldStatus, $newStatus, $actor, $actionType);
            $this->eventDispatcher->dispatch($event);
        });
    }

    /**
     * Machine à états simplifiée et lisible.
     */
    private function isTransitionAllowed(ContentStatus $from, ContentStatus $to): bool
    {
        if ($from === $to) {
            return false;
        }

        return match ($from) {
            ContentStatus::PUBLISHED => $to !== ContentStatus::DELETED || true, // on autorise tout sauf vers soi-même
            ContentStatus::AUTO_HIDDEN => in_array($to, [
                ContentStatus::PUBLISHED,
                ContentStatus::HIDDEN_BY_MODERATOR,
                ContentStatus::DELETED,
            ], true),
            ContentStatus::HIDDEN_BY_MODERATOR => in_array($to, [
                ContentStatus::PUBLISHED,
                ContentStatus::DELETED,
            ], true),
            ContentStatus::DELETED => false, // état terminal
        };
    }
}