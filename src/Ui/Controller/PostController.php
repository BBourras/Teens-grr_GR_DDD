<?php

declare(strict_types=1);

namespace App\Ui\Controller;

use App\Application\Dto\CreatePostDto;
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
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * PostController – Gestion des Posts (affichage, CRUD).
 *
 * Ce controller reste fin et délègue toute la logique métier aux Services.
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
    // LISTES PUBLIQUES
    // ======================================================

    #[Route('/recent', name: 'post_recent', methods: ['GET'])]
    public function recent(Request $request): Response
    {
        $pagination = $this->paginator->paginate(
            $this->postService->getLatestQueryBuilder(),
            $request->query->getInt('page', 1),
            15
        );

        $posts = $pagination->getItems();

        return $this->render('post/recents.html.twig', [
            'pagination' => $pagination,
            'postScores' => $this->getPostScoresFromEntities($posts),
        ]);
    }

    #[Route('/top', name: 'post_trending', methods: ['GET'])]
    public function trending(Request $request): Response
    {
        $posts = $this->postService->getTrendingPosts(15);

        return $this->render('post/trending.html.twig', [
            'posts'      => $posts,
            'postScores' => $this->getPostScoresFromArrays($posts),   // Adaptation car SQL natif
            'userVotes'  => $this->getUserVotes($posts, $request),
        ]);
    }

    #[Route('/legends', name: 'post_legend', methods: ['GET'])]
    public function legends(Request $request): Response
    {
        $posts = $this->postService->getLegendPosts(15);

        return $this->render('post/legends.html.twig', [
            'posts'      => $posts,
            'postScores' => $this->getPostScoresFromArrays($posts),
            'userVotes'  => $this->getUserVotes($posts, $request),
        ]);
    }

    // ======================================================
    // AFFICHAGE D'UN POST
    // ======================================================

    #[Route('/{id<\d+>}', name: 'post_show', methods: ['GET'])]
    public function show(Post $post, Request $request): Response
    {
        return $this->render('post/show.html.twig', [
            'post'         => $post,
            'comments'     => $post->getComments(),
            'postScores'   => $this->voteService->getVoteScoreByType($post),
            'userVote'     => $this->getCurrentUserVote($post, $request),
            'reportReasons' => ReportReason::cases(),
        ]);
    }

    // ======================================================
    // CRUD
    // ======================================================

    #[Route('/new', name: 'post_create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request): Response
    {
        $form = $this->createForm(PostFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var CreatePostDto $dto */
            $dto = $form->getData();

            $post = $this->postService->createPost($dto, $this->getUser());

            $this->addFlash('success', 'Post publié avec succès !');

            return $this->redirectToRoute('post_show', [
                'id' => $post->getId()
            ]);
        }

        return $this->render('post/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'post_edit', methods: ['GET', 'POST'])]
    public function edit(Post $post, Request $request): Response
    {
        $this->denyAccessUnlessGranted('POST_EDIT', $post);

        $form = $this->createForm(PostFormType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->postService->update(
                $post,
                $post->getTitle(),
                $post->getContent()
            );

            $this->addFlash('success', 'Post modifié avec succès.');

            return $this->redirectToRoute('post_show', [
                'id' => $post->getId()
            ]);
        }

        return $this->render('post/edit.html.twig', [
            'form' => $form->createView(),
            'post' => $post,
        ]);
    }

    #[Route('/{id}/delete', name: 'post_delete', methods: ['POST'])]
    public function delete(Post $post, Request $request): Response
    {
        $this->denyAccessUnlessGranted('POST_DELETE', $post);

        if (!$this->isCsrfTokenValid('delete' . $post->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('post_show', ['id' => $post->getId()]);
        }

        $this->postService->deleteByAuthor($post, $this->getUser());

        $this->addFlash('success', 'Post supprimé avec succès.');

        return $this->redirectToRoute('app_home');
    }

    // ======================================================
    // HELPERS
    // ======================================================

    /**
     * Calcule les scores pour un tableau d'entités Post.
     */
    private function getPostScoresFromEntities(iterable $posts): array
    {
        $scores = [];
        foreach ($posts as $post) {
            $scores[$post->getId()] = $this->voteService->getVoteScoreByType($post);
        }
        return $scores;
    }

    /**
     * Calcule les scores pour un tableau issu de SQL natif (Trending / Legend).
     * Attention : les posts sont des arrays ici.
     */
    private function getPostScoresFromArrays(iterable $posts): array
    {
        $scores = [];
        foreach ($posts as $post) {
            // Si c'est un array issu de SQL natif, on recharge l'entité
            if (is_array($post) && isset($post['id'])) {
                $entity = $this->postService->findPostById($post['id']); // À créer si nécessaire
                if ($entity) {
                    $scores[$post['id']] = $this->voteService->getVoteScoreByType($entity);
                }
            }
        }
        return $scores;
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

    private function getUserVotes(array $posts, Request $request): array
    {
        $votes = [];
        $user = $this->getUser();
        $guestKey = $request->cookies->get('guest_vote_key');

        foreach ($posts as $post) {
            $id = is_array($post) ? $post['id'] : $post->getId();

            if ($user) {
                $vote = $this->voteService->getUserVoteOnPost($post, $user);
            } elseif ($guestKey) {
                $vote = $this->voteService->getGuestVoteOnPost($post, $guestKey);
            } else {
                $vote = null;
            }

            $votes[$id] = $vote?->getType()?->value;
        }

        return $votes;
    }
}
