<?php

declare(strict_types=1);

namespace App\Ui\Controller;

use App\Application\Service\PostService;
use App\Application\Service\VoteService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Affiche les derniers posts + les posts les plus populaires sur la page d'accueil.
 */
class HomeController extends AbstractController
{
    public function __construct(
        private readonly PostService $postService,
        private readonly VoteService $voteService,
        private readonly PaginatorInterface $paginator,
    ) {}

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $queryBuilder = $this->postService->getLatestPosts();

        $pagination = $this->paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            6 // nombre de posts sur la home
        );

        // Récupération des posts mis en avant
        $topDuMoment = $this->postService->getTrendingPosts(5);
        $legends    = $this->postService->getLegendPosts(5);

        // Calcul des scores détaillés par emoji pour TOUS les posts affichés
        $allPosts = array_merge(
            $pagination->getItems(),
            $topDuMoment,
            $legends
        );

        $postScores = [];

        foreach ($allPosts as $post) {
            $postScores[$post->getId()] = [
                'scores' => $this->voteService->getScoreByTypeForPost($post),
            ];
        }

        return $this->render('home/index.html.twig', [
            'pagination'   => $pagination,
            'topDuMoment'  => $topDuMoment,
            'legends'     => $legends,
            'postScores'   => $postScores,        // ← Nécessaire pour afficher les emojis
        ]);
    }
}
