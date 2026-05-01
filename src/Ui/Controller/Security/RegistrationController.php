<?php

declare(strict_types=1);

namespace App\Ui\Controller\Security;

use App\Domain\Entity\User;
use App\Ui\Form\RegistrationFormType;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;

class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserAuthenticatorInterface $userAuthenticator,
        private readonly FormLoginAuthenticator $formLoginAuthenticator,
    ) {}

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        // Redirection si déjà connecté
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // Hash du mot de passe
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword(
                $this->passwordHasher->hashPassword($user, $plainPassword)
            );

            try {
                $this->em->persist($user);
                $this->em->flush();

                $this->addFlash('success', 'Votre compte a été créé avec succès ! Vous êtes maintenant connecté.');

                // Connexion automatique après inscription
                return $this->userAuthenticator->authenticateUser(
                    $user,
                    $this->formLoginAuthenticator,
                    $request
                ) ?? $this->redirectToRoute('app_home');

            } catch (UniqueConstraintViolationException) {
                $this->addFlash(
                    'error',
                    'Cet email ou ce pseudo est déjà utilisé. Veuillez en choisir un autre.'
                );
            }
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}