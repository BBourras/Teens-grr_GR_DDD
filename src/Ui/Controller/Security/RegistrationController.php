<?php

declare(strict_types=1);

namespace App\Ui\Controller\Security;

use App\Domain\Entity\User;
use App\Ui\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserAuthenticatorInterface $userAuthenticator,
        private readonly FormLoginAuthenticator $formLoginAuthenticator,
        private readonly RateLimiterFactory $registrationLimiter,   
    ) {}

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        // Rate limiting
        $limiter = $this->registrationLimiter->create($request->getClientIp() ?? 'anonymous');
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $this->addFlash('error', 'Trop de tentatives d’inscription. Veuillez réessayer plus tard.');
            return $this->redirectToRoute('app_register');
        }

        $user = new User(); 
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();

            $user->setPassword(
                $this->passwordHasher->hashPassword($user, $plainPassword)
            );

            $this->em->persist($user);
            $this->em->flush();

            $this->addFlash('success', 'Votre compte a été créé avec succès !');

            return $this->userAuthenticator->authenticateUser(
                $user,
                $this->formLoginAuthenticator,
                $request
            ) ?? $this->redirectToRoute('app_home');
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}