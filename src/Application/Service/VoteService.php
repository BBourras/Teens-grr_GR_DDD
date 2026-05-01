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
 * VoteService – Couche Application pure.
 *
 * Responsabilités :
 * - Gérer le cycle de vie complet d’un vote (création / mise à jour / suppression)
 * - Appliquer les règles métier : 1 vote par post pour les connectés, rate limit pour les invités
 * - Déclencher uniquement des événements domaine (le scoring est géré par VoteScoreListener)
 *
 * Tout est event-driven : le service ne touche jamais directement reactionScore.
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
        $this->handleVote(
            $post,
            $type,
            guestKey: $guestKey,
            guestIpRaw: $guestIpRaw
        );
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

            // Rate limiting uniquement pour les invités
            if ($user === null) {
                $this->enforceGuestRateLimit($guestIpRaw);
                $this->assertValidGuestKey($guestKey);
            }

            $existingVote = $this->findExistingVote($post, $user, $guestKey);

            if ($existingVote === null) {
                $this->createVote($post, $type, $user, $guestKey, $guestIpRaw);
                return;
            }

            // Même type → on supprime (toggle off)
            if ($existingVote->getType() === $type) {
                $this->removeVote($existingVote);
                return;
            }

            // Type différent → on met à jour
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
        $vote = Vote::createForPost($post, $type);   // constructeur riche recommandé

        if ($user !== null) {
            $vote->assignUser($user);
        } else {
            $vote->assignGuest($guestKey, $guestIpRaw ? $this->hashIp($guestIpRaw) : null);
        }

        $this->em->persist($vote);

        $this->dispatchEvent(VoteEvent::CREATED, $post, $vote, null, $type);
    }

    private function removeVote(Vote $vote): void
    {
        $post = $vote->getPost();
        $oldType = $vote->getType();

        $this->em->remove($vote);

        $this->dispatchEvent(VoteEvent::REMOVED, $post, $vote, $oldType, null);
    }

    private function updateVote(Vote $vote, VoteType $newType): void
    {
        $oldType = $vote->getType();
        $vote->setType($newType);

        $this->dispatchEvent(VoteEvent::UPDATED, $vote->getPost(), $vote, $oldType, $newType);
    }

    private function dispatchEvent(
        string $eventName,
        Post $post,
        Vote $vote,
        ?VoteType $oldType,
        ?VoteType $newType
    ): void {
        $this->dispatcher->dispatch(
            new VoteEvent($eventName, $post, $vote, $oldType, $newType)
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
        $result = $limiter->consume(1);

        if (!$result->isAccepted()) {
            throw new TooManyRequestsHttpException(
                retryAfter: $result->getRetryAfter()?->getTimestamp() ?? time() + 30,
                message: 'Vous avez atteint la limite de votes anonymes (5 par 24h). Revenez plus tard.'
            );
        }
    }

    private function assertValidGuestKey(?string $guestKey): void
    {
        if (empty($guestKey)) {
            throw new \LogicException('Une clé invité est obligatoire pour voter en mode anonyme.');
        }
    }

    private function hashIp(string $ip): string
    {
        return hash('sha256', $ip . 'salt_for_anonymity'); // sel léger pour plus de sécurité
    }

    // ======================================================
    // MÉTHODES DE LECTURE (commodité)
    // ======================================================

    public function getUserVoteOnPost(Post $post, User $user): ?Vote
    {
        return $this->voteRepository->findOneByUserAndPost($user, $post);
    }

    public function getGuestVoteOnPost(Post $post, string $guestKey): ?Vote
    {
        return $this->voteRepository->findOneByGuestAndPost($guestKey, $post);
    }

    public function getVoteScoreByType(Post $post): array
    {
        return $this->voteRepository->findScoreByTypeForPost($post);
    }
}