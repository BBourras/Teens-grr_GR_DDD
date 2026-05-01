<?php

declare(strict_types=1);

namespace App\Infrastructure\EventListener;

use App\Domain\Event\VoteEvent;
use App\Domain\Entity\Post;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * VoteScoreListener – Met à jour le reactionScore dénormalisé des Posts.
 *
 * Ce listener est le seul endroit autorisé à modifier reactionScore.
 * Il écoute VoteEvent et applique l’impact du vote (pondéré via VoteType::scoreImpact()).
 *
 * Avantages :
 * - Découplage total : VoteService ne touche jamais au score
 * - Atomicité grâce à la transaction du service
 * - Facile à tester et à étendre
 */
#[AsEventListener(event: VoteEvent::class)]
final class VoteScoreListener
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function __invoke(VoteEvent $event): void
    {
        $post = $event->getPost();

        // On ne met à jour que les Posts (les Comments n'ont pas de reactionScore)
        if (!$post instanceof Post) {
            return;
        }

        $delta = $event->getScoreDelta();

        // Si le delta est nul, on ne fait rien (ex: toggle identique)
        if ($delta === 0) {
            return;
        }

        // Mise à jour du score dénormalisé
        $this->updateReactionScore($post, $delta);

        // Flush immédiat car nous sommes dans la transaction du VoteService
        $this->entityManager->flush();
    }

    /**
     * Met à jour le reactionScore du Post de manière sécurisée.
     */
    private function updateReactionScore(Post $post, int $delta): void
    {
        $newScore = max(0, $post->getReactionScore() + $delta);

        // On utilise une méthode métier du Post plutôt qu'un setter direct
        $post->setReactionScore($newScore);   // à ajouter dans Post si elle n'existe pas encore

        // Optionnel : logger en cas de score très élevé ou négatif (debug)
        if ($newScore > 1000 || $newScore < 0) {
            // Tu peux ajouter un logger ici si besoin
        }
    }
}