<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Contract\VoteRepositoryInterface;
use App\Domain\Entity\Post;
use App\Domain\Entity\User;
use App\Domain\Entity\Vote;
use App\Domain\Enum\VoteType;
use App\Domain\Event\VoteEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * ======================================================
 * 🧠 VoteService
 * ======================================================
 *
 * Responsabilités :
 * - Orchestration métier des votes
 * - Gestion user / guest
 * - Toggle vote
 * - Application des règles anti-abus
 * - Dispatch d'événements domaine
 *
 * ⚠️ IMPORTANT :
 * - Aucune logique SQL ici
 * - Aucune agrégation
 * - Seulement logique métier
 */
final class VoteService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VoteRepositoryInterface $voteRepository,
        private readonly RateLimiterFactory $voteGuestLimiter,
        private readonly EventDispatcherInterface $dispatcher,
    ) {}

    // ======================================================
    // PUBLIC API
    // ======================================================

    public function voteAsUser(Post $post, User $user, VoteType $type): void
    {
        $this->handleVote($post, $type, user: $user);
    }

    public function voteAsGuest(Post $post, string $guestKey, string $guestIpRaw, VoteType $type): void
    {
        $this->handleVote($post, $type, guestKey: $guestKey, guestIpRaw: $guestIpRaw);
    }

    // ======================================================
    // CORE LOGIC
    // ======================================================

    private function handleVote(
        Post $post,
        VoteType $type,
        ?User $user = null,
        ?string $guestKey = null,
        ?string $guestIpRaw = null,
    ): void {
        $this->em->wrapInTransaction(function () use ($post, $type, $user, $guestKey, $guestIpRaw) {

            if ($user === null) {
                $this->enforceGuestRateLimit($guestIpRaw);
            }

            $existingVote = $this->findExistingVote($post, $user, $guestKey);

            // CREATE
            if ($existingVote === null) {
                $this->createVote($post, $type, $user, $guestKey, $guestIpRaw);
                return;
            }

            // TOGGLE DELETE
            if ($existingVote->getType() === $type) {
                $this->removeVote($existingVote);
                return;
            }

            // UPDATE
            $this->updateVote($existingVote, $type);
        });
    }

    // ======================================================
    // DOMAIN ACTIONS
    // ======================================================

    private function createVote(
        Post $post,
        VoteType $type,
        ?User $user,
        ?string $guestKey,
        ?string $guestIpRaw
    ): void {
        $vote = new Vote($post, $type);   // suppose que tu as un constructeur riche

        if ($user !== null) {
            $vote->assignUser($user);
        } else {
            $vote->assignGuest($guestKey, $this->hashIp($guestIpRaw));
        }

        $this->em->persist($vote);

        $this->dispatcher->dispatch(
            new VoteEvent(VoteEvent::CREATED, $post, $vote, null, $type)
        );
    }

    private function removeVote(Vote $vote): void
    {
        $oldType = $vote->getType();
        $post = $vote->getPost();

        $this->em->remove($vote);

        $this->dispatcher->dispatch(
            new VoteEvent(VoteEvent::REMOVED, $post, $vote, $oldType, null)
        );
    }

    private function updateVote(Vote $vote, VoteType $newType): void
    {
        $oldType = $vote->getType();
        $vote->setType($newType);

        $this->dispatcher->dispatch(
            new VoteEvent(VoteEvent::UPDATED, $vote->getPost(), $vote, $oldType, $newType)
        );
    }

    // ======================================================
    // HELPERS
    // ======================================================

    private function findExistingVote(Post $post, ?User $user, ?string $guestKey): ?Vote
    {
        return $user !== null
            ? $this->voteRepository->findOneByUserAndPost($user, $post)
            : $this->voteRepository->findOneByGuestAndPost($guestKey, $post);
    }

    private function enforceGuestRateLimit(string $guestIpRaw): void
    {
        $limiter = $this->voteGuestLimiter->create($this->hashIp($guestIpRaw));
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(
                retryAfter: $limit->getRetryAfter()?->getTimestamp() ?? time() + 60,
                message: 'Trop de votes anonymes. Réessayez plus tard.'
            );
        }
    }

    private function hashIp(?string $ip): string
    {
        return hash('sha256', ($ip ?? 'unknown') . 'teens_grr_salt_2026');
    }

    // ======================================================
    // READ HELPERS (UI)
    // ======================================================

    /**
     * Récupère les votes groupés par type pour affichage UI
     */
    public function getVoteScoreByType(Post $post): array
    {
        return $this->voteRepository->findScoreByTypeForPost($post);
    }
}
