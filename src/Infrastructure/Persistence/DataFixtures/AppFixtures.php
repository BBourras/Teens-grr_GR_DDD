<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\DataFixtures;

use App\Application\Dto\CreateCommentDto;
use App\Application\Dto\CreatePostDto;
use App\Application\Service\CommentService;
use App\Application\Service\PostService;
use App\Domain\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly PostService $postService,
        private readonly CommentService $commentService,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function load(ObjectManager $manager): void
    {
        // ======================================================
        // CRÉATION D'UTILISATEURS
        // ======================================================

        $user = new User();
        $user->setEmail('professeur@teens-grr.fr');
        $user->setUsername('ProfIronique');
        $user->setRoles(['ROLE_USER']);

        $user->setPassword(
            $this->passwordHasher->hashPassword($user, 'password123')
        );

        $manager->persist($user);
        $manager->flush();

        // ======================================================
        // CRÉATION DE POSTS (avec DTO)
        // ======================================================

        $postDto1 = new CreatePostDto(
            title: "Les ados et leur addiction au téléphone portable",
            content: "Aujourd'hui en cours, j'ai vu un élève de 15 ans répondre à sa mère par \"ok boomer\" parce que je lui ai demandé de ranger son téléphone..."
        );

        $post1 = $this->postService->createPost($postDto1, $user);

        $postDto2 = new CreatePostDto(
            title: "Quand ils disent \"c'est compliqué\" pour tout",
            content: "Pourquoi les ados transforment-ils la moindre consigne en drame shakespearien ?"
        );

        $post2 = $this->postService->createPost($postDto2, $user);

        // ======================================================
        // CRÉATION DE COMMENTAIRES (avec DTO)
        // ======================================================

        $commentDto1 = new CreateCommentDto(
            content: "Tellement vrai ! J'ai le même combat tous les jours en classe."
        );
        $this->commentService->createComment($commentDto1, $user, $post1);

        $commentDto2 = new CreateCommentDto(
            content: "Le pire c'est quand ils font ça pendant que tu leur parles en face..."
        );
        $this->commentService->createComment($commentDto2, $user, $post2);

        $manager->flush();

        echo "Fixtures chargées avec succès !\n";
    }
}