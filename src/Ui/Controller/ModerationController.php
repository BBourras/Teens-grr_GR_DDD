<?php

declare(strict_types=1);

namespace App\Ui\Controller;

use App\Domain\Entity\Comment;
use App\Domain\Entity\Post;
use App\Application\Service\CommentService;
use App\Application\Service\ModerationService;
use App\Application\Service\PostService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/moderation')]
class ModerationController extends AbstractController
{
    public function __construct(
        private readonly ModerationService $moderationService,
        private readonly PostService $postService,
        private readonly CommentService $commentService,
    ) {}

    #[Route('', name: 'moderation_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_MODERATOR')]
    public function dashboard(): Response
    {
        return $this->render('moderation/dashboard.html.twig', [
            'posts'    => $this->postService->getAutoHiddenPendingPosts(),
            'comments' => $this->commentService->getAutoHiddenPendingComments(),
        ]);
    }

    // Actions Posts
    #[Route('/post/{id}/hide', name: 'moderation_post_hide', methods: ['POST'])]
    #[IsGranted('ROLE_MODERATOR')]
    public function hidePost(Post $post, Request $request): Response
    {
        $this->processAction($post, 'hideByModerator', $request);
        return $this->redirectToRoute('moderation_dashboard');
    }

    #[Route('/post/{id}/restore', name: 'moderation_post_restore', methods: ['POST'])]
    #[IsGranted('ROLE_MODERATOR')]
    public function restorePost(Post $post, Request $request): Response
    {
        $this->processAction($post, 'restore', $request);
        return $this->redirectToRoute('moderation_dashboard');
    }

    #[Route('/post/{id}/delete', name: 'moderation_post_delete', methods: ['POST'])]
    #[IsGranted('ROLE_MODERATOR')]
    public function deletePost(Post $post, Request $request): Response
    {
        $this->processAction($post, 'deleteByModerator', $request);
        return $this->redirectToRoute('moderation_dashboard');
    }

    // Actions Commentaires
    #[Route('/comment/{id}/hide', name: 'moderation_comment_hide', methods: ['POST'])]
    #[IsGranted('ROLE_MODERATOR')]
    public function hideComment(Comment $comment, Request $request): Response
    {
        $this->processAction($comment, 'hideByModerator', $request);
        return $this->redirectToRoute('moderation_dashboard');
    }

    #[Route('/comment/{id}/restore', name: 'moderation_comment_restore', methods: ['POST'])]
    #[IsGranted('ROLE_MODERATOR')]
    public function restoreComment(Comment $comment, Request $request): Response
    {
        $this->processAction($comment, 'restore', $request);
        return $this->redirectToRoute('moderation_dashboard');
    }

    #[Route('/comment/{id}/delete', name: 'moderation_comment_delete', methods: ['POST'])]
    #[IsGranted('ROLE_MODERATOR')]
    public function deleteComment(Comment $comment, Request $request): Response
    {
        $this->processAction($comment, 'deleteByModerator', $request);
        return $this->redirectToRoute('moderation_dashboard');
    }

    private function processAction($content, string $action, Request $request): void
    {
        if (!$this->isCsrfTokenValid('moderation_' . $content->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return;
        }

        /** @var User $moderator */
        $moderator = $this->getUser();
        $reason = $request->request->get('reason');

        $this->moderationService->$action($content, $moderator, $reason);

        $this->addFlash('success', 'Action effectuée avec succès.');
    }
}