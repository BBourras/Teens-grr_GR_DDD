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
 * VoteService – Gestion des votes/réactions.
 *
 * Responsabilités :
 * - Appliquer les règles métier (1 vote max par user/post, rate limit pour invités)
 * - Créer, mettre à jour ou supprimer un vote
 * - Déclencher uniquement des événements domaine (le calcul du score est géré par un listener)
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
    // API PUBLIQUE
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
    // LOGIQUE CENTRALE
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

            if ($existingVote === null) {
                $this->createVote($post, $type, $user, $guestKey, $guestIpRaw);
                return;
            }

            // Toggle : même type → suppression
            if ($existingVote->getType() === $type) {
                $this->removeVote($existingVote);
                return;
            }

            // Changement de type
            $this->updateVote($existingVote, $type);
        });
    }

    // ======================================================
    // ACTIONS MÉTIER
    // ======================================================

    private function createVote(
        Post $post,
        VoteType $type,
        ?User $user,
        ?string $guestKey,
        ?string $guestIpRaw
    ): void {
        $vote = new Vote($post, $type);

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
        $post = $vote->getPost();
        $oldType = $vote->getType();

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
                message: 'Vous avez atteint la limite de votes anonymes (5 par 24h).'
            );
        }
    }

    private function hashIp(?string $ip): string
    {
        if ($ip === null) {
            return 'unknown_' . uniqid();
        }
        return hash('sha256', $ip . 'teens_grr_salt');
    }

    // ======================================================
    // MÉTHODES DE LECTURE (commodité)
    // ======================================================

    public function getUserVoteOnPost(Post $post, User $user): ?Vote
    {
        return $this->voteRepository->findOneByUserAndPost($user, $post);
    }

    public function getVoteScoreByType(Post $post): array
    {
        return $this->voteRepository->findScoreByTypeForPost($post);
    }
}