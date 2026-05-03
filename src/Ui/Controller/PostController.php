<?php

declare(strict_types=1);

namespace App\Ui\Controller;

use App\Domain\Entity\Post;
use App\Domain\Enum\ReportReason;
use App\Ui\Form\PostFormType;
use App\Application\Service\PostService;
use App\Application\Service\VoteService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/posts')]
class PostController extends AbstractController
{
    public function __construct(
        private readonly PostService $postService,
        private readonly VoteService $voteService,
        private readonly PaginatorInterface $paginator,
    ) {}

    #[Route('/recent', name: 'post_recent', methods: ['GET'])]
    public function recent(Request $request): Response
    {
        $pagination = $this->paginator->paginate(
            $this->postService->getLatestQueryBuilder(),
            $request->query->getInt('page', 1),
            15
        );

        $postScores = $this->getPostScores($pagination->getItems());

        return $this->render('post/recents.html.twig', [
            'pagination' => $pagination,
            'postScores' => $postScores,
        ]);
    }

    #[Route('/top', name: 'post_trending', methods: ['GET'])]
    public function trending(Request $request): Response
    {
        $posts = $this->postService->getTrendingPosts(15);
        $postScores = $this->getPostScores($posts);
        $userVotes = $this->getUserVotes($posts, $request);

        return $this->render('post/trending.html.twig', [
            'posts'      => $posts,
            'postScores' => $postScores,
            'userVotes'  => $userVotes,
        ]);
    }

    #[Route('/legends', name: 'post_legend', methods: ['GET'])]
    public function legends(Request $request): Response
    {
        $posts = $this->postService->getLegendPosts(15);
        $postScores = $this->getPostScores($posts);
        $userVotes = $this->getUserVotes($posts, $request);

        return $this->render('post/legends.html.twig', [
            'posts'      => $posts,
            'postScores' => $postScores,
            'userVotes'  => $userVotes,
        ]);
    }

    #[Route('/{id}', name: 'post_show', methods: ['GET'])]
    public function show(Post $post, Request $request): Response
    {
        $userVote = $this->getCurrentUserVote($post, $request);

        return $this->render('post/show.html.twig', [
            'post'         => $post,
            'comments'     => $post->getComments(),
            'postScores'   => $this->voteService->getVoteScoreByType($post),
            'userVote'     => $userVote,
            'reportReasons'=> ReportReason::cases(),
        ]);
    }

    // ======================================================
    // HELPERS
    // ======================================================

    private function getPostScores(array $posts): array
    {
        $scores = [];
        foreach ($posts as $post) {
            $scores[$post->getId()] = $this->voteService->getVoteScoreByType($post);
        }
        return $scores;
    }

    private function getUserVotes(array $posts, Request $request): array
    {
        $votes = [];
        $user = $this->getUser();
        $guestKey = $request->cookies->get('guest_vote_key');

        foreach ($posts as $post) {
            if ($user) {
                $vote = $this->voteService->getUserVoteOnPost($post, $user);
            } elseif ($guestKey) {
                $vote = $this->voteService->getGuestVoteOnPost($post, $guestKey);
            } else {
                $vote = null;
            }
            $votes[$post->getId()] = $vote?->getType()?->value;
        }

        return $votes;
    }

    private function getCurrentUserVote(Post $post, Request $request): ?string
    {
        $user = $this->getUser();
        if ($user) {
            $vote = $this->voteService->getUserVoteOnPost($post, $user);
            return $vote?->getType()?->value;
        }

        $guestKey = $request->cookies->get('guest_vote_key');
        if ($guestKey) {
            $vote = $this->voteService->getGuestVoteOnPost($post, $guestKey);
            return $vote?->getType()?->value;
        }

        return null;
    }
}