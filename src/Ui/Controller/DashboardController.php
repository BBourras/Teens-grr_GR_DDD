<?php

declare(strict_types=1);

namespace App\Ui\Controller;

use App\Application\Formatter\ContentStatusFormatter;
use App\Application\Formatter\ReportReasonFormatter;   // ← Ajout
use App\Domain\Entity\User;
use App\Infrastructure\Persistence\Repository\CommentRepository;
use App\Infrastructure\Persistence\Repository\PostRepository as RepositoryPostRepository;
use App\Infrastructure\Persistence\Repository\ReportRepository;
use App\Infrastructure\Persistence\Repository\ModerationActionLogRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * DashboardController - Gestion du dashboard personnel de l'utilisateur.
 */
#[Route('/my-account')]
#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly RepositoryPostRepository $postRepository,
        private readonly CommentRepository $commentRepository,
        private readonly ReportRepository $reportRepository,
        private readonly ModerationActionLogRepository $logRepository,
        private readonly PaginatorInterface $paginator,
        private readonly ContentStatusFormatter $statusFormatter,
        private readonly ReportReasonFormatter $reportReasonFormatter,   // ← Injecté
    ) {}

    /**
     * Tableau de bord principal
     */
    #[Route('', name: 'user_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('dashboard/dashboard.html.twig', [
            'user'                    => $user,
            'statusFormatter'         => $this->statusFormatter,
            'report_reason_formatter' => $this->reportReasonFormatter,   // ← Passé au template

            'myPosts'          => $this->postRepository->findBy(['author' => $user], ['createdAt' => 'DESC'], 20),
            'myComments'       => $this->commentRepository->findBy(['author' => $user], ['createdAt' => 'DESC'], 30),
            'myReports'        => $this->reportRepository->findBy(['user' => $user], ['createdAt' => 'DESC'], 15),
            'myModerationLogs' => $this->logRepository->findByAffectedUser($user, 30),
        ]);
    }

    // ======================================================
    // AUTRES PAGES DU DASHBOARD
    // ======================================================

    #[Route('/posts', name: 'user_my_posts', methods: ['GET'])]
    public function myPosts(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $queryBuilder = $this->postRepository->createQueryBuilder('p')
            ->where('p.author = :user')
            ->setParameter('user', $user)
            ->orderBy('p.createdAt', 'DESC');

        $pagination = $this->paginator->paginate($queryBuilder, $request->query->getInt('page', 1), 12);

        return $this->render('dashboard/my_posts.html.twig', [
            'pagination'      => $pagination,
            'statusFormatter' => $this->statusFormatter,
        ]);
    }

    #[Route('/comments', name: 'user_my_comments', methods: ['GET'])]
    public function myComments(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('dashboard/my_comments.html.twig', [
            'comments'        => $this->commentRepository->findBy(['author' => $user], ['createdAt' => 'DESC']),
            'statusFormatter' => $this->statusFormatter,
        ]);
    }

    #[Route('/reports', name: 'user_my_reports', methods: ['GET'])]
    public function myReports(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('dashboard/my_reports.html.twig', [
            'reports'                 => $this->reportRepository->findBy(['user' => $user], ['createdAt' => 'DESC']),
            'statusFormatter'         => $this->statusFormatter,
            'report_reason_formatter' => $this->reportReasonFormatter,   // ← Important pour ce template
        ]);
    }

    #[Route('/historique', name: 'user_moderation_history', methods: ['GET'])]
    public function moderationHistory(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('dashboard/moderation_history.html.twig', [
            'logs'                    => $this->logRepository->findByAffectedUser($user, 50),
            'statusFormatter'         => $this->statusFormatter,
            'report_reason_formatter' => $this->reportReasonFormatter,
        ]);
    }
}