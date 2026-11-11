<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** View own profile and change own password - available to every logged-in role. */
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em, private UserPasswordHasherInterface $hasher)
    {
    }

    #[Route('/profile', name: 'app_profile')]
    public function profile(): Response
    {
        return $this->render('profile/index.html.twig', ['me' => $this->getUser()]);
    }

    #[Route('/profile/password', name: 'app_profile_password', methods: ['GET', 'POST'])]
    public function password(Request $request, TokenStorageInterface $tokenStorage): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('change_password', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }
            $current = $request->request->getString('current_password');
            $new = $request->request->getString('new_password');
            $confirm = $request->request->getString('confirm_password');

            if (!$this->hasher->isPasswordValid($user, $current)) {
                $errors[] = 'Your current password is not correct.';
            }
            if (strlen($new) < 8) {
                $errors[] = 'The new password must be at least 8 characters.';
            }
            if ($new !== $confirm) {
                $errors[] = 'The new password and its confirmation do not match.';
            }
            if (!$errors) {
                $user->setPassword($this->hasher->hashPassword($user, $new));
                $this->em->flush();
                // End this session so the person signs in again with the new password.
                $tokenStorage->setToken(null);
                $request->getSession()->invalidate();
                $this->addFlash('success', 'Password changed. Please log in again with your new password.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('profile/password.html.twig', ['errors' => $errors], new Response('', $errors ? 422 : 200));
    }
}
