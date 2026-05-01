<?php

declare(strict_types=1);

namespace App\Ui\Controller;

use App\Domain\Entity\Comment;
use App\Domain\Entity\Post;
use App\Ui\Form\CommentFormType;
use App\Application\Service\CommentService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller gérant les Commentaires.
 *
 * Délégation des droits :
 * ---------------------------------------------------
 * - Création    → ROLE_USER (access_control)
 * - Suppression → CommentVoter (COMMENT_DELETE)
 *
 * Sécurité supplémentaire :
 * ---------------------------------------------------
 * - Vérification que le commentaire appartient bien au post de l'URL (défense en profondeur contre les URLs forgées)
 * - Token CSRF vérifié sur la suppression et sur la création
 */
#[Route('/posts/{id}/comments')]
class CommentController extends AbstractController
{
    public function __construct(
        private readonly CommentService $commentService,
    ) {}

    /**
     * Création d'un commentaire sur un post.
     *
     * La protection ROLE_USER est assurée par access_control
     * dans security.yaml. Le denyAccessUnlessGranted est conservé
     * comme filet de sécurité si la route est appelée directement.
     */
    #[Route('', name: 'comment_create', methods: ['POST'])]
    public function create(Post $post, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $comment = new Comment();
        $form    = $this->createForm(CommentFormType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->commentService->createComment(
                $comment->getContent(),
                $this->getUser(),
                $post
            );

            $this->addFlash('success', 'Commentaire ajouté avec succès.');
        } else {
            $this->addFlash('error', 'Votre commentaire ne doit pas être vide ou trop long.');
        }

        return $this->redirectToRoute('post_show', ['id' => $post->getId()]);
    }

    /**
     * Suppression logique d'un commentaire.
     * - Permission gérée par CommentVoter (COMMENT_DELETE)
     * - Vérification que le commentaire appartient au post de l'URL
     * - Token CSRF vérifié pour protéger contre les requêtes forgées
     */
    #[Route('/{commentId}/delete', name: 'comment_delete', methods: ['POST'])]
    public function delete(
        Post $post,
        #[MapEntity(mapping: ['commentId' => 'id'])] Comment $comment,
        Request $request,
    ): Response {
        $this->denyAccessUnlessGranted('COMMENT_DELETE', $comment);

        // Défense en profondeur : empêche de supprimer un commentaire d'un autre post en forgeant l'URL.
        if ($comment->getPost() !== $post) {
            throw $this->createNotFoundException('Commentaire non trouvé pour ce post.');
        }

        if (!$this->isCsrfTokenValid('delete_comment_' . $comment->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute(
                'post_show',
                ['id' => $post->getId()]
            );
        }

        $this->commentService->deleteByAuthor($comment, $this->getUser());
        $this->addFlash('success', 'Commentaire supprimé avec succès.');

        return $this->redirectToRoute('post_show', ['id' => $post->getId()]);
    }
}
