<?php

declare(strict_types=1);

namespace App\Ui\Controller;

use App\Domain\Entity\Post;
use App\Domain\Enum\VoteType;
use App\Application\Service\VoteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Controller de vote :
 * - délègue toute la logique métier au VoteService
 * - gère uniquement HTTP + cookies + flash messages
 */
#[Route('/posts/{id}/vote')]
class VoteController extends AbstractController
{
    private const GUEST_COOKIE_NAME = 'guest_vote_key';
    private const COOKIE_TTL = 31536000; // 1 an

    public function __construct(
        private readonly VoteService $voteService,
    ) {}

    #[Route('', name: 'vote_post', methods: ['POST'])]
    public function vote(Post $post, Request $request): Response
    {
        // =========================
        // 1. Validation du type de vote
        // =========================
        $type = VoteType::tryFrom($request->request->get('type'));

        if ($type === null) {
            $this->addFlash('error', 'Type de vote invalide.');
            return $this->redirectToRoute('post_show', ['id' => $post->getId()]);
        }

        $user = $this->getUser();

        // =========================
        // 2. Cas utilisateur connecté
        // =========================
        if ($user) {
            $this->voteService->voteAsUser($post, $user, $type);

            $this->addFlash('success', 'Vote enregistré !');

            return $this->redirectToRoute('post_show', ['id' => $post->getId()]);
        }

        // =========================
        // 3. Cas invité
        // =========================
        $guestKey = $request->cookies->get(self::GUEST_COOKIE_NAME);

        if (!$guestKey) {
            $guestKey = Uuid::v4()->toRfc4122();
        }

        try {
            $this->voteService->voteAsGuest(
                post: $post,
                guestKey: $guestKey,
                guestIpRaw: $request->getClientIp() ?? '',
                type: $type
            );

            $this->addFlash('success', 'Vote enregistré !');

        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        $response = $this->redirectToRoute('post_show', ['id' => $post->getId()]);

        // Pose cookie invité
        $response->headers->setCookie(
            Cookie::create(self::GUEST_COOKIE_NAME)
                ->withValue($guestKey)
                ->withExpires(time() + self::COOKIE_TTL)
                ->withHttpOnly(true)
                ->withSameSite(Cookie::SAMESITE_LAX)
        );

        return $response;
    }
}