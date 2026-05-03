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
        $pagination = $this->paginator->paginate(
            $this->postService->getLatestQueryBuilder(),
            $request->query->getInt('page', 1),
            6
        );

        $topDuMoment = $this->postService->getTrendingPosts(5);
        $legends     = $this->postService->getLegendPosts(5);

        // Scores pour tous les posts affichés
        $allPosts = array_merge(
            $pagination->getItems(),
            $topDuMoment,
            $legends
        );

        $postScores = [];
        foreach ($allPosts as $post) {
            $postScores[$post->getId()] = $this->voteService->getVoteScoreByType($post);
        }

        return $this->render('home/index.html.twig', [
            'pagination'   => $pagination,
            'topDuMoment'  => $topDuMoment,
            'legends'      => $legends,
            'postScores'   => $postScores,
        ]);
    }
}