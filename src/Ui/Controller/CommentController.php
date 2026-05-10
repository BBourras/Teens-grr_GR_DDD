<?php

declare(strict_types=1);

namespace App\Ui\Controller;

use App\Application\Dto\CreateCommentDto;
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
 * CommentController – Gestion des commentaires.
 */
#[Route('/posts/{postId}/comments')]
class CommentController extends AbstractController
{
    public function __construct(
        private readonly CommentService $commentService,
    ) {}

    #[Route('', name: 'comment_create', methods: ['POST'])]
    public function create(
        #[MapEntity(mapping: ['postId' => 'id'])] Post $post,
        Request $request
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $form = $this->createForm(CommentFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var CreateCommentDto $dto */
            $dto = $form->getData();

            $this->commentService->createComment($dto, $this->getUser(), $post);

            $this->addFlash('success', 'Le commentaire a été ajouté avec succès.');
        } else {
            $this->addFlash('error', 'Le commentaire ne peut pas être vide.');
        }

        return $this->redirectToRoute('post_show', ['id' => $post->getId()]);
    }

    #[Route('/{commentId}/delete', name: 'comment_delete', methods: ['POST'])]
    public function delete(
        #[MapEntity(mapping: ['postId' => 'id'])] Post $post,
        #[MapEntity(mapping: ['commentId' => 'id'])] Comment $comment,
        Request $request
    ): Response {
        $this->denyAccessUnlessGranted('COMMENT_DELETE', $comment);

        // Sécurité supplémentaire : le commentaire doit bien appartenir au post
        if ($comment->getPost()->getId() !== $post->getId()) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('delete_comment_' . $comment->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('post_show', ['id' => $post->getId()]);
        }

        $this->commentService->deleteByAuthor($comment, $this->getUser());

        $this->addFlash('success', 'Le commentaire a été supprimé.');

        return $this->redirectToRoute('post_show', ['id' => $post->getId()]);
    }
}