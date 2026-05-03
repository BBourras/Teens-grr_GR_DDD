<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Contract\VoteRepositoryInterface;
use App\Domain\Entity\Post;
use App\Domain\Entity\User;
use App\Domain\Entity\Vote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * ======================================================
 * 📦 VoteRepository
 * ======================================================
 *
 * Implémente VoteRepositoryInterface (contrat domaine)
 *
 * Responsabilités :
 * - Accès aux votes
 * - Agrégations simples
 * - Recherche user / guest
 * - Statistiques par post
 *
 * ⚠️ Aucune logique métier ici
 */
class VoteRepository extends ServiceEntityRepository implements VoteRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vote::class);
    }

    // ======================================================
    // 🔍 LOOKUPS (EXISTING VOTES)
    // ======================================================

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
            'post' => $post,
        ]);
    }

    // ======================================================
    // 📊 BASIC STATS
    // ======================================================

    public function countByPost(Post $post): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.post = :post')
            ->setParameter('post', $post)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Liste complète des votes d’un post
     */
    public function findAllByPost(Post $post): array
    {
        return $this->findBy(
            ['post' => $post],
            ['createdAt' => 'DESC']
        );
    }

    // ======================================================
    // 📊 VOTES GROUPÉS PAR TYPE
    // ======================================================

    /**
     * Retourne :
     * [
     *   'LAUGH' => 12,
     *   'ANGRY' => 3,
     *   ...
     * ]
     */
    public function findScoreByTypeForPost(Post $post): array
    {
        $rows = $this->createQueryBuilder('v')
            ->select('v.type AS type, COUNT(v.id) AS count')
            ->where('v.post = :post')
            ->groupBy('v.type')
            ->setParameter('post', $post)
            ->getQuery()
            ->getArrayResult();

        $result = [];

        foreach ($rows as $row) {
            $result[$row['type']] = (int) $row['count'];
        }

        return $result;
    }

    // ======================================================
    // 🚫 ANTI-SPAM (GUEST IP)
    // ======================================================

    /**
     * Compte les votes récents d’un invité (rate limiting)
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