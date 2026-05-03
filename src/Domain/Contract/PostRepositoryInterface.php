<?php

declare(strict_types=1);

namespace App\Domain\Contract;

use App\Domain\Entity\User;
use Doctrine\ORM\QueryBuilder;

interface PostRepositoryInterface
{

    //Retourne les posts les plus récents visibles publiquement.
    public function findLatestPosts(int $limit = 10): array;

    public function createLatestQueryBuilder(): QueryBuilder;

    //Retourne les posts trending (score pondéré avec déclin temporel
    public function findTrendingPosts(int $limit = 10): array;

    //Retourne les posts "légendes" (score durable, sans déclin temporel)
    public function findLegendPosts(int $limit = 10): array;

    // Retourne les posts masqués automatiquement en attente de modération
    public function findAutoHiddenPendingPosts(): array;

    // Retourne les posts visibles d'un auteur
    public function findVisibleByAuthor(User $author, int $limit = 10): array;

    //Compte le nombre total de posts visibles
    public function countVisible(): int;
}