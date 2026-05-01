<?php

declare(strict_types=1);

namespace App\Ui\Controller;

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
 * DashboardController - Gestion du dashboard personnel de l'utilisateur
 *
 * Ce contrôleur centralise toutes les vues liées au profil utilisateur :
 * - Tableau de bord général
 * - Mes posts, mes commentaires, mes signalements
 * - Historique complet de modération
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
    ) {}

    /**
     * Tableau de bord principal de l'utilisateur
     */
    #[Route('', name: 'user_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('dashboard/dashboard.html.twig', [
            'user' => $user,

            'myPosts' => $this->postRepository->findBy(
                ['author' => $user],
                ['createdAt' => 'DESC'],
                20
            ),

            'myComments' => $this->commentRepository->findBy(
                ['author' => $user],
                ['createdAt' => 'DESC'],
                30
            ),

            'myReports' => $this->reportRepository->findBy(
                ['user' => $user],
                ['createdAt' => 'DESC'],
                15
            ),

            'myModerationLogs' => $this->logRepository->findModActByUser($user, 30),
        ]);
    }

    /**
     * Liste complète des posts de l'utilisateur avec pagination
     */
    #[Route('/posts', name: 'user_my_posts', methods: ['GET'])]
    public function myPosts(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $queryBuilder = $this->postRepository->createQueryBuilder('p')
            ->where('p.author = :user')
            ->setParameter('user', $user)
            ->orderBy('p.createdAt', 'DESC');

        $pagination = $this->paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            12
        );

        return $this->render('dashboard/my_posts.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    /**
     * Liste des commentaires publiés par l'utilisateur
     */
    #[Route('/comments', name: 'user_my_comments', methods: ['GET'])]
    public function myComments(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('dashboard/my_comments.html.twig', [
            'comments' => $this->commentRepository->findBy(
                ['author' => $user],
                ['createdAt' => 'DESC']
            ),
        ]);
    }

    /**
     * Liste des signalements effectués par l'utilisateur
     */
    #[Route('/reports', name: 'user_my_reports', methods: ['GET'])]
    public function myReports(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('dashboard/my_reports.html.twig', [
            'reports' => $this->reportRepository->findBy(
                ['user' => $user],
                ['createdAt' => 'DESC']
            ),
        ]);
    }

    /**
     * Historique complet des actions de modération sur les contenus de l'utilisateur
     */
    #[Route('/historique', name: 'user_moderation_history', methods: ['GET'])]
    public function moderationHistory(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('dashboard/moderation_history.html.twig', [
            'logs' => $this->logRepository->findModActByUser($user, 50),
        ]);
    }
}