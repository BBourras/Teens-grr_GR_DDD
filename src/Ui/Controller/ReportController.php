<?php

declare(strict_types=1);

namespace App\Ui\Controller;

use App\Domain\Entity\Comment;
use App\Domain\Entity\Post;
use App\Ui\Form\ReportFormType;
use App\Application\Service\ReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reports')]
class ReportController extends AbstractController
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    // ====================== AFFICHAGE DU FORMULAIRE (GET) ======================

    #[Route('/posts/{id}/report', name: 'report_post_form', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function reportPostForm(Post $post): Response
    {
        $form = $this->createForm(ReportFormType::class);

        return $this->render('reports/report_form.html.twig', [
            'form'    => $form->createView(),
            'content' => $post,
            'type'    => 'post',
        ]);
    }

    #[Route('/comments/{id}/report', name: 'report_comment_form', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function reportCommentForm(Comment $comment): Response
    {
        $form = $this->createForm(ReportFormType::class);

        return $this->render('reports/report_form.html.twig', [
            'form'    => $form->createView(),
            'content' => $comment,
            'type'    => 'comment',
        ]);
    }

    // ====================== TRAITEMENT DU SIGNALEMENT (POST) ======================

    #[Route('/posts/{id}/report', name: 'report_post', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function reportPost(Post $post, Request $request): Response
    {
        return $this->handleReport($post, $request);
    }

    #[Route('/comments/{id}/report', name: 'report_comment', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function reportComment(Comment $comment, Request $request): Response
    {
        return $this->handleReport($comment, $request);
    }

    /**
     * Traitement du signalement (uniquement POST)
     */
    private function handleReport($content, Request $request): Response
    {
        $form = $this->createForm(ReportFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $reason = $data['reason'] ?? null;
            $reasonDetail = $data['reason_detail'] ?? null;

                        $user = $this->getUser();

            try {
                if ($content instanceof Post) {
                    $this->reportService->reportPost($content, $user, $reason, $reasonDetail);
                    $this->addFlash('success', 'Le post a bien été signalé.');
                } else {
                    $this->reportService->reportComment($content, $user, $reason, $reasonDetail);
                    $this->addFlash('success', 'Le commentaire a bien été signalé. Merci pour votre vigilance.');
                }
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors du signalement.');
            }

            return $this->redirectToPost($content);
        }

        // Si le formulaire est invalide, on réaffiche la page
        return $this->render('reports/report_form.html.twig', [
            'form'    => $form->createView(),
            'content' => $content,
            'type'    => $content instanceof Post ? 'post' : 'comment',
        ]);
    }

    private function redirectToPost($content): Response
    {
        $postId = $content instanceof Post
            ? $content->getId()
            : $content->getPost()->getId();

        return $this->redirectToRoute('post_show', ['id' => $postId]);
    }
}
