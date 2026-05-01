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

/**
 * PostController
 *
 * Responsabilités :
 * -----------------------------------------------------
 * - Affichage des posts (recent / trending / legends / show)
 * - CRUD posts (create / edit / delete)
 * - Aucune logique métier de vote (déléguée à VoteService)
 */
#[Route('/posts')]
class PostController extends AbstractController
{
    public function __construct(
        private readonly PostService $postService,
        private readonly VoteService $voteService,
        private readonly PaginatorInterface $paginator,
    ) {}

    // ======================================================
    // LISTE DES POSTS
    // ======================================================

    #[Route('/recent', name: 'post_recent', methods: ['GET'])]
    public function recent(Request $request): Response
    {
        $qb = $this->postService->getLatestPostsQueryBuilder();

        $pagination = $this->paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            15
        );

        $postScores = [];

        foreach ($pagination->getItems() as $post) {
            $postScores[$post->getId()] =
                $this->voteService->getScoreByTypeForPost($post);
        }

        return $this->render('post/recents.html.twig', [
            'pagination' => $pagination,
            'postScores' => $postScores,
        ]);
    }

    // ======================================================
    // TRENDING POSTS
    // ======================================================

    #[Route('/top', name: 'post_trending', methods: ['GET'])]
    public function top(Request $request): Response
    {
        $posts = $this->postService->getTrendingPosts(15);

        $postScores = [];
        $userVotes = [];

        foreach ($posts as $post) {

            $postScores[$post->getId()] =
                $this->voteService->getScoreByTypeForPost($post);

            if ($user = $this->getUser()) {
                $vote = $this->voteService->getUserVote($post, $user);
                $userVotes[$post->getId()] = $vote?->getType()->value;
            } elseif ($guestKey = $request->cookies->get('guest_vote_key')) {
                $vote = $this->voteService->getGuestVote($post, $guestKey);
                $userVotes[$post->getId()] = $vote?->getType()->value;
            }
        }

        return $this->render('post/trending.html.twig', [
            'posts'      => $posts,
            'postScores' => $postScores,
            'userVotes'  => $userVotes,
        ]);
    }

    // ======================================================
    // LEGENDS POSTS
    // ======================================================

    #[Route('/legends', name: 'post_legend', methods: ['GET'])]
    public function legends(Request $request): Response
    {
        $posts = $this->postService->getLegendPosts(15);

        $postScores = [];
        $userVotes = [];

        foreach ($posts as $post) {

            $postScores[$post->getId()] =
                $this->voteService->getScoreByTypeForPost($post);

            if ($user = $this->getUser()) {
                $vote = $this->voteService->getUserVote($post, $user);
                $userVotes[$post->getId()] = $vote?->getType()->value;
            } elseif ($guestKey = $request->cookies->get('guest_vote_key')) {
                $vote = $this->voteService->getGuestVote($post, $guestKey);
                $userVotes[$post->getId()] = $vote?->getType()->value;
            }
        }

        return $this->render('post/legends.html.twig', [
            'posts'      => $posts,
            'postScores' => $postScores,
            'userVotes'  => $userVotes,
        ]);
    }

    // ======================================================
    // AFFICHAGE D'UN POST
    // ======================================================

    #[Route('/{id}', name: 'post_show', methods: ['GET'])]
    public function show(Post $post, Request $request): Response
    {
        $userVote = null;

        if ($user = $this->getUser()) {
            $vote = $this->voteService->getUserVote($post, $user);
            $userVote = $vote?->getType()->value;
        } elseif ($guestKey = $request->cookies->get('guest_vote_key')) {
            $vote = $this->voteService->getGuestVote($post, $guestKey);
            $userVote = $vote?->getType()->value;
        }

        return $this->render('post/show.html.twig', [
            'post'         => $post,
            'comments'     => $post->getComments(),
            'postScores'   => $this->voteService->getScoreByTypeForPost($post),
            'userVote'     => $userVote,
            'reportReasons'=> ReportReason::cases(),
        ]);
    }

    // ======================================================
    // CREATE POST
    // ======================================================

    #[Route('/new', name: 'post_create', methods: ['GET','POST'])]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $post = new Post();

        $form = $this->createForm(PostFormType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $created = $this->postService->createPost(
                $post->getTitle(),
                $post->getContent(),
                $this->getUser()
            );

            return $this->redirectToRoute('post_show', [
                'id' => $created->getId()
            ]);
        }

        return $this->render('post/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    // ======================================================
    // EDIT POST 
    // ======================================================

    #[Route('/{id}/edit', name: 'post_edit', methods: ['GET','POST'])]
    public function edit(Post $post, Request $request): Response
    {
        $this->denyAccessUnlessGranted('POST_EDIT', $post);

        $form = $this->createForm(PostFormType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $this->postService->update($post);

            return $this->redirectToRoute('post_show', [
                'id' => $post->getId()
            ]);
        }

        return $this->render('post/edit.html.twig', [
            'form' => $form->createView(),
            'post' => $post,
        ]);
    }

    // ======================================================
    // DELETE POST
    // ======================================================

    #[Route('/{id}/delete', name: 'post_delete', methods: ['POST'])]
    public function delete(Post $post, Request $request): Response
    {
        $this->denyAccessUnlessGranted('POST_DELETE', $post);

        if (!$this->isCsrfTokenValid('delete_post_'.$post->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide');
            return $this->redirectToRoute('post_show', ['id' => $post->getId()]);
        }

        $this->postService->deleteByAuthor($post, $this->getUser());

        return $this->redirectToRoute('post_recent');
    }
}