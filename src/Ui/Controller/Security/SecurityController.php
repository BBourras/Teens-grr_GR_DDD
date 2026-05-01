<?php

declare(strict_types=1);

namespace App\Ui\Controller\Security;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Redirection si déjà connecté
        if ($this->getUser()) {
            $this->addFlash('info', 'Vous êtes déjà connecté.');
            return $this->redirectToRoute('app_home');
        }

        // Dernière erreur de connexion (credentials invalides, compte banni, etc.)
        $error = $authenticationUtils->getLastAuthenticationError();

        // Dernier identifiant saisi (pré-remplit le champ email)
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error'         => $error,
        ]);
    }

    /**
     * La déconnexion est interceptée par le firewall Symfony avant
     * d'atteindre ce corps de méthode. La LogicException garantit
     * qu'un appel direct (hors firewall) échoue explicitement.
     */
    #[Route('/logout', name: 'app_logout')]
    public function logout(): never
    {
        throw new \LogicException(
            'Cette route est interceptée par le firewall. '
            . 'Vérifiez la configuration de security.yaml.'
        );
    }
}