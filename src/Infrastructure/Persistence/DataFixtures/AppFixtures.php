<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\DataFixtures;

use App\Domain\Entity\User;
use App\Domain\Enum\ReportReason;
use App\Domain\Enum\VoteType;
use App\Application\Service\CommentService;
use App\Application\Service\ModerationService;
use App\Application\Service\PostService;
use App\Application\Service\ReportService;
use App\Application\Service\VoteService;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Faker\Factory;
/**
 * Fixtures pour tester l'application blog ironique sur les ados.
 *
 * Utilise les Services pour respecter l'architecture DDD.
 * Commande : php bin/console doctrine:fixtures:load --append
 */
class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PostService $postService,
        private readonly CommentService $commentService,
        private readonly VoteService $voteService,
        private readonly ReportService $reportService,
        private readonly ModerationService $moderationService,
    ) {}

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // ======================================================
        // 1️⃣ USERS (Admin + Modérateur + 10 users)
        // ======================================================

        $users = [];

        // Admin
        $admin = new User();
        $admin->setEmail('admin@test.com');
        $admin->setUsername('Admin');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);
        $users[] = $admin;

        // Modérateur
        $mod = new User();
        $mod->setEmail('mod@test.com');
        $mod->setUsername('Moderator');
        $mod->setRoles(['ROLE_MODERATOR']);
        $mod->setPassword($this->passwordHasher->hashPassword($mod, 'mod123'));
        $manager->persist($mod);
        $users[] = $mod;

        // Users classiques
        for ($i = 1; $i <= 10; $i++) {
            $user = new User();
            $user->setEmail("user$i@test.com");
            $user->setUsername($faker->userName());
            $user->setRoles(['ROLE_USER']);
            $user->setPassword($this->passwordHasher->hashPassword($user, '1234_toto'));

            $manager->persist($user);
            $users[] = $user;
        }

        $manager->flush();

        // ======================================================
        // 2️⃣ POSTS (50 posts) via PostService
        // ======================================================

        $posts = [];

        for ($i = 0; $i < 50; $i++) {
            $author = $users[array_rand($users)];
            $title = $faker->sentence(6);
            $content = $faker->paragraphs(3, true);

            $post = $this->postService->createPost($title, $content, $author);

            $roll = \random_int(1, 100);

            if ($roll <= 65) {
                // publié
            } elseif ($roll <= 80) {
                $this->moderationService->autoHide($post);
            } elseif ($roll <= 95) {
                $this->moderationService->hideByModerator(
                    $post,
                    $mod,
                    'Contenu inapproprié pour un blog à destination des éducateurs.'
                );
            } else {
                $this->moderationService->deleteByAuthor($post, $author);
            }

            $posts[] = $post;
        }

        $manager->flush();

        // ======================================================
        // 3️⃣ COMMENTAIRES via CommentService
        // ======================================================

        foreach ($posts as $post) {
            $commentCount = \random_int(0, 6);

            for ($i = 0; $i < $commentCount; $i++) {
                $author = $users[array_rand($users)];
                $content = $faker->sentence(12);

                $this->commentService->createComment($content, $author, $post);
            }
        }

        $manager->flush();

        // ======================================================
        // 4️⃣ VOTES via VoteService (connectés + invités)
        // ======================================================

        foreach ($posts as $post) {
            foreach ($users as $user) {
                if (\random_int(1, 100) <= 75) {
                    $roll = \random_int(1, 100);

                    $voteType = match (true) {
                        $roll <= 60 => VoteType::LAUGH,
                        $roll <= 85 => VoteType::DISILLUSIONED,
                        default     => VoteType::ANGRY,
                    };

                    $this->voteService->voteAsUser($post, $user, $voteType);
                }
            }

            if (\random_int(1, 100) <= 40) {
                $guestIp = $faker->ipv4();
                $guestKey = 'guest_' . bin2hex(\random_bytes(8));

                $this->voteService->voteAsGuest($post, $guestKey, $guestIp, VoteType::LAUGH);
            }
        }

        $manager->flush();

        // ======================================================
        // 5️⃣ REPORTS via ReportService
        // ======================================================

        foreach ($posts as $post) {
            if (\random_int(1, 100) <= 40) {
                $reporter = $users[array_rand($users)];

                $this->reportService->reportPost(
                    $post,
                    $reporter,
                    ReportReason::INAPPROPRIATE,
                    $faker->sentence(8)
                );

                if ($post->getReportCount() >= 5) {
                    $this->moderationService->autoHide($post);
                }
            }
        }

        $manager->flush();

        // ======================================================
        // 6️⃣ MODÉRATION MANUELLE
        // ======================================================

        if (!empty($posts)) {
            $firstPost = $posts[0];

            $this->moderationService->hideByModerator(
                $firstPost,
                $mod,
                'Contenu trop provocateur pour un blog à destination des éducateurs.'
            );

            if (\random_int(1, 100) <= 30) {
                $this->moderationService->restore(
                    $firstPost,
                    $mod,
                    'Signalement infirmé après vérification.'
                );
            }
        }

        $manager->flush();
    }
}