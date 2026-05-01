<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Contract\VoteRepositoryInterface;
use App\Domain\Entity\Post;
use App\Domain\Entity\Vote;
use App\Domain\Entity\User;
use App\Domain\Enum\VoteType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository des votes/réactions.
 *
 * Gestion des deux types de votants :
 * - Utilisateur connecté → via la relation User
 * - Invité               → via guestKey
 *
 * La limitation anti-abus pour les invités repose sur guestIpHash (et non sur guestKey).
 */
class VoteRepository extends ServiceEntityRepository implements VoteRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vote::class);
    }

    public function findOneByUserAndPost(User $user, Post $post): ?Vote
    {
        return $this->findOneBy([
            'user' => $user,
            'post' => $post,
        ]);
    }

    public function findOneByGuestAndPost(string $guestKey, Post $post): ?Vote
    {
        return $this->findOneBy([
            'guestKey' => $guestKey,
            'post'     => $post,
        ]);
    }

    public function countByPost(Post $post): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.post = :post')
            ->setParameter('post', $post)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findAllByPost(Post $post): array
    {
        return $this->findBy(['post' => $post], ['createdAt' => 'DESC']);
    }

    /**
     * Retourne le nombre de votes par type pour un post.
     *
     * @return array<string, int>  ex: ['laugh' => 12, 'angry' => 3, 'disillusioned' => 8]
     */
    public function findScoreByTypeForPost(Post $post): array
    {
        $rows = $this->createQueryBuilder('v')
            ->select('v.type AS type, COUNT(v.id) AS voteCount')
            ->where('v.post = :post')
            ->groupBy('v.type')
            ->setParameter('post', $post)
            ->getQuery()
            ->getResult();

        // Initialisation à zéro pour tous les types
        $score = [];
        foreach (VoteType::cases() as $case) {
            $score[$case->value] = 0;
        }

        // Remplissage avec les résultats réels
        foreach ($rows as $row) {
            $key = $row['type'] instanceof VoteType 
                ? $row['type']->value 
                : (string) $row['type'];
            $score[$key] = (int) $row['voteCount'];
        }

        return $score;
    }

    /**
     * Compte les votes récents d’un invité identifié par son IP hashée (anti-abus).
     */
    public function countRecentVotesByIpHash(
        Post $post,
        string $guestIpHash,
        \DateTimeInterface $since
    ): int {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.post = :post')
            ->andWhere('v.guestIpHash = :ipHash')
            ->andWhere('v.createdAt >= :since')
            ->setParameter('post', $post)
            ->setParameter('ipHash', $guestIpHash)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }
}