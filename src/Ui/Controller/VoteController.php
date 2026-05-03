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
        $type = VoteType::tryFrom($request->request->get('type'));

        if ($type === null) {
            $this->addFlash('error', 'Type de réaction invalide.');
            return $this->redirectToRoute('post_show', ['id' => $post->getId()]);
        }

        $user = $this->getUser();

        if ($user) {
            $this->voteService->voteAsUser($post, $user, $type);
            $this->addFlash('success', 'Vote enregistré !');
        } else {
            $guestKey = $request->cookies->get(self::GUEST_COOKIE_NAME) ?? Uuid::v4()->toRfc4122();

            try {
                $this->voteService->voteAsGuest($post, $guestKey, $request->getClientIp() ?? '', $type);
                $this->addFlash('success', 'Vote enregistré !');
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        $response = $this->redirectToRoute('post_show', ['id' => $post->getId()]);

        // Mise à jour du cookie invité
        if (!$user) {
            $response->headers->setCookie(
                Cookie::create(self::GUEST_COOKIE_NAME)
                    ->withValue($guestKey)
                    ->withExpires(time() + self::COOKIE_TTL)
                    ->withHttpOnly(true)
                    ->withSameSite(Cookie::SAMESITE_LAX)
            );
        }

        return $response;
    }
}