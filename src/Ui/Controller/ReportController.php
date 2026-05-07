<?php

declare(strict_types=1);

namespace App\Ui\Controller;

use App\Domain\Contract\ModeratableContentInterface;
use App\Domain\Entity\Comment;
use App\Domain\Entity\Post;
use App\Ui\Form\ReportFormType;
use App\Application\Service\ReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * ReportController – Gestion des signalements par les utilisateurs.
 */
#[Route('/reports')]
class ReportController extends AbstractController
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    #[Route('/posts/{id}/report', name: 'report_post', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function reportPost(Post $post, Request $request): Response
    {
        return $this->handleReport($post, $request);
    }

    #[Route('/comments/{id}/report', name: 'report_comment', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function reportComment(Comment $comment, Request $request): Response
    {
        return $this->handleReport($comment, $request);
    }

    /**
     * Logique commune de signalement (Post ou Comment).
     *
     * @param Post|Comment $content
     */
    private function handleReport(
        ModeratableContentInterface $content,
        Request $request
    ): Response {
        $form = $this->createForm(ReportFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            try {
                if ($content instanceof Post) {
                    $this->reportService->reportPost(
                        $content,
                        $this->getUser(),
                        $data['reason'],
                        $data['reason_detail'] ?? null
                    );
                } else {
                    $this->reportService->reportComment(
                        $content,
                        $this->getUser(),
                        $data['reason'],
                        $data['reason_detail'] ?? null
                    );
                }

                $this->addFlash('success', 'Signalement enregistré. Merci pour votre vigilance !');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors du signalement.');
            }

            return $this->redirectToContent($content);
        }

        return $this->render('reports/report_form.html.twig', [
            'form'    => $form->createView(),
            'content' => $content,
            'type'    => $content instanceof Post ? 'post' : 'comment',
        ]);
    }

    private function redirectToContent(ModeratableContentInterface $content): Response
    {
        $postId = $content instanceof Post 
            ? $content->getId() 
            : $content->getPost()->getId();

        return $this->redirectToRoute('post_show', ['id' => $postId]);
    }
}