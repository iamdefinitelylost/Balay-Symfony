<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $auth): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_post_login');
        }

        $error = $auth->getLastAuthenticationError();

        return $this->render('security/login.html.twig', [
            'last_username' => $auth->getLastUsername(),
            'error' => $error ? $error->getMessageKey() : null,
        ]);
    }

    /** Sends each role to its own landing page after login. */
    #[Route('/post-login', name: 'app_post_login')]
    public function postLogin(): RedirectResponse
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin');
        }
        if ($this->isGranted('ROLE_STAFF')) {
            return $this->redirectToRoute('app_records');
        }

        return $this->redirectToRoute('home');
    }

    /** Intercepted by the firewall's logout handler; this body never runs. */
    #[Route('/logout', name: 'app_logout')]
    public function logout(): never
    {
        throw new \LogicException('Handled by the logout key in security.yaml.');
    }
}
