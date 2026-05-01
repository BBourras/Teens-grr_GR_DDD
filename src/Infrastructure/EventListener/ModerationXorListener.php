<?php

declare(strict_types=1);

namespace App\Infrastructure\EventListener;

use App\Domain\Contract\XorTargetInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;

/**
 * Listener Doctrine qui applique la contrainte XOR sur les entités
 * qui implémentent XorTargetInterface (Report et ModerationActionLog).
 *
 * Appelé automatiquement avant chaque persistance pour garantir
 * qu'une entité cible exactement un Post OU un Comment.
 *
 * La logique métier est déléguée à XorTargetInterface::assertExactlyOneTarget().
 */
#[AsDoctrineListener(event: Events::prePersist)]
final class ModerationXorListener
{
    /**
     * Vérifie la contrainte XOR avant insertion en base.
     */
    public function prePersist(object $entity): void
    {
        if (!$entity instanceof XorTargetInterface) {
            return;
        }

        $entity->assertExactlyOneTarget();
    }
}