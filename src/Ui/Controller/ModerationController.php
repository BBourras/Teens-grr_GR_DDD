<?php

declare(strict_types=1);

namespace App\Ui\Controller;

use App\Domain\Contract\ModeratableContentInterface;
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
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * ModerationController – Dashboard et actions manuelles de modération.
 *
 * Centralise toutes les actions de modération (masquage, suppression, restauration)
 * pour les Posts et Commentaires.
 */
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
            'pendingPosts'    => $this->postService->getAutoHiddenPendingPosts(),
            'pendingComments' => $this->commentService->getAutoHiddenPendingComments(),
        ]);
    }

    // ======================================================
    // ACTIONS SUR POSTS
    // ======================================================

    #[Route('/post/{id}/hide', name: 'moderation_post_hide', methods: ['POST'])]
    #[IsGranted('ROLE_MODERATOR')]
    public function hidePost(Post $post, Request $request): Response
    {
        $this->executeModerationAction($post, 'hideByModerator', $request, 'Post masqué avec succès.');
        return $this->redirectToRoute('moderation_dashboard');
    }

    #[Route('/post/{id}/restore', name: 'moderation_post_restore', methods: ['POST'])]
    #[IsGranted('ROLE_MODERATOR')]
    public function restorePost(Post $post, Request $request): Response
    {
        $this->executeModerationAction($post, 'restore', $request, 'Post restauré avec succès.');
        return $this->redirectToRoute('moderation_dashboard');
    }

    #[Route('/post/{id}/delete', name: 'moderation_post_delete', methods: ['POST'])]
    #[IsGranted('ROLE_MODERATOR')]
    public function deletePost(Post $post, Request $request): Response
    {
        $this->executeModerationAction($post, 'deleteByModerator', $request, 'Post supprimé définitivement.');
        return $this->redirectToRoute('moderation_dashboard');
    }

    // ======================================================
    // ACTIONS SUR COMMENTAIRES
    // ======================================================

    #[Route('/comment/{id}/hide', name: 'moderation_comment_hide', methods: ['POST'])]
    #[IsGranted('ROLE_MODERATOR')]
    public function hideComment(Comment $comment, Request $request): Response
    {
        $this->executeModerationAction($comment, 'hideByModerator', $request, 'Commentaire masqué avec succès.');
        return $this->redirectToRoute('moderation_dashboard');
    }

    #[Route('/comment/{id}/restore', name: 'moderation_comment_restore', methods: ['POST'])]
    #[IsGranted('ROLE_MODERATOR')]
    public function restoreComment(Comment $comment, Request $request): Response
    {
        $this->executeModerationAction($comment, 'restore', $request, 'Commentaire restauré avec succès.');
        return $this->redirectToRoute('moderation_dashboard');
    }

    #[Route('/comment/{id}/delete', name: 'moderation_comment_delete', methods: ['POST'])]
    #[IsGranted('ROLE_MODERATOR')]
    public function deleteComment(Comment $comment, Request $request): Response
    {
        $this->executeModerationAction($comment, 'deleteByModerator', $request, 'Commentaire supprimé définitivement.');
        return $this->redirectToRoute('moderation_dashboard');
    }

    // ======================================================
    // MÉTHODE PRIVÉE
    // ======================================================

    /**
     * Exécute une action de modération de façon sécurisée.
     */
    private function executeModerationAction(
        ModeratableContentInterface $content,
        string $actionMethod,
        Request $request,
        string $successMessage
    ): void {
        if (!$this->isCsrfTokenValid('moderation_' . $content->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return;
        }

        /** @var User $moderator */
        $moderator = $this->getUser();
        $reason = $request->request->get('reason');

        // Appel dynamique sécurisé
        $this->moderationService->$actionMethod($content, $moderator, $reason);

        $this->addFlash('success', $successMessage);
    }
}