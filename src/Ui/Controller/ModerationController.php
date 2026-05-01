<?php

declare(strict_types=1);

namespace App\Ui\Controller;

use App\Domain\Entity\Comment;
use App\Domain\Entity\Post;
use App\Domain\Entity\User;
use App\Application\Service\CommentService;
use App\Application\Service\ModerationService;
use App\Application\Service\PostService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/moderation')]
class ModerationController extends AbstractController
{
    public function __construct(
        private readonly ModerationService $moderationService,
        private readonly PostService $postService,
        private readonly CommentService $commentService,
    ) {}

    #[Route('', name: 'moderation_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MODERATOR');

        return $this->render('moderation/dashboard.html.twig', [
            'posts'    => $this->postService->getAutoHiddenPosts(),
            'comments' => $this->commentService->getAutoHiddenPendingComments(),
        ]);
    }

    // ======================================================
    // ACTIONS SUR LES POSTS
    // ======================================================

    #[Route('/posts/{id}/hide', name: 'moderation_post_hide', methods: ['POST'])]
    public function hidePost(Post $post, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MODERATOR');

        if (!$this->verifyCsrf('moderation_post_' . $post->getId(), $request)) {
            return $this->redirectToRoute('moderation_dashboard');
        }

        /** @var User $moderator */
        $moderator = $this->getUser();

        $this->moderationService->hideByModerator($post, $moderator, $request->request->get('reason'));

        $this->addFlash('success', 'Post masqué manuellement.');

        return $this->redirectToRoute('moderation_dashboard');
    }

    #[Route('/posts/{id}/restore', name: 'moderation_post_restore', methods: ['POST'])]
    public function restorePost(Post $post, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MODERATOR');

        if (!$this->verifyCsrf('moderation_post_' . $post->getId(), $request)) {
            return $this->redirectToRoute('moderation_dashboard');
        }

        /** @var User $moderator */
        $moderator = $this->getUser();

        $this->moderationService->restore($post, $moderator, $request->request->get('reason'));

        $this->addFlash('success', 'Post restauré et republié.');

        return $this->redirectToRoute('moderation_dashboard');
    }

    #[Route('/posts/{id}/delete', name: 'moderation_post_delete', methods: ['POST'])]
    public function deletePost(Post $post, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MODERATOR');

        if (!$this->verifyCsrf('moderation_post_' . $post->getId(), $request)) {
            return $this->redirectToRoute('moderation_dashboard');
        }

        /** @var User $moderator */
        $moderator = $this->getUser();

        $this->moderationService->deleteByModerator($post, $moderator, $request->request->get('reason'));

        $this->addFlash('success', 'Post supprimé.');

        return $this->redirectToRoute('moderation_dashboard');
    }

    // ======================================================
    // ACTIONS SUR LES COMMENTAIRES
    // ======================================================

    #[Route('/comments/{id}/hide', name: 'moderation_comment_hide', methods: ['POST'])]
    public function hideComment(Comment $comment, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MODERATOR');

        if (!$this->verifyCsrf('moderation_comment_' . $comment->getId(), $request)) {
            return $this->redirectToRoute('moderation_dashboard');
        }

        /** @var User $moderator */
        $moderator = $this->getUser();

        $this->moderationService->hideByModerator($comment, $moderator, $request->request->get('reason'));

        $this->addFlash('success', 'Commentaire masqué.');

        return $this->redirectToRoute('moderation_dashboard');
    }

    #[Route('/comments/{id}/restore', name: 'moderation_comment_restore', methods: ['POST'])]
    public function restoreComment(Comment $comment, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MODERATOR');

        if (!$this->verifyCsrf('moderation_comment_' . $comment->getId(), $request)) {
            return $this->redirectToRoute('moderation_dashboard');
        }

        /** @var User $moderator */
        $moderator = $this->getUser();

        $this->moderationService->restore($comment, $moderator, $request->request->get('reason'));

        $this->addFlash('success', 'Commentaire restauré.');

        return $this->redirectToRoute('moderation_dashboard');
    }

    #[Route('/comments/{id}/delete', name: 'moderation_comment_delete', methods: ['POST'])]
    public function deleteComment(Comment $comment, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MODERATOR');

        if (!$this->verifyCsrf('moderation_comment_' . $comment->getId(), $request)) {
            return $this->redirectToRoute('moderation_dashboard');
        }

        /** @var User $moderator */
        $moderator = $this->getUser();

        $this->moderationService->deleteByModerator($comment, $moderator, $request->request->get('reason'));

        $this->addFlash('success', 'Commentaire supprimé.');

        return $this->redirectToRoute('moderation_dashboard');
    }

    private function verifyCsrf(string $tokenId, Request $request): bool
    {
        if (!$this->isCsrfTokenValid($tokenId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide. Action annulée.');
            return false;
        }
        return true;
    }
}
