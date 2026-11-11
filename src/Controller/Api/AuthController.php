<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\PasswordResetService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Login and logout are handled by the firewall in config/packages/security.yaml
 * (POST /api/auth/login with JSON, and /logout). Only sign-up and "who am I" live here.
 */
#[Route('/api/auth')]
class AuthController extends ApiController
{
    public function __construct(
        private UserRepository $users,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private PasswordResetService $resets,
    ) {
    }

    #[Route('/me', methods: ['GET'])]
    public function me(Request $request): JsonResponse
    {
        return $this->json(['user' => $this->sessionUser($request)]);
    }

    /**
     * The firewall's json_login handles this URL before the controller runs, but Symfony still
     * needs a route to exist for it (otherwise the router answers 404 first).
     * It only runs if the request was not sent as JSON.
     */
    #[Route('/login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        return $this->json(['error' => 'Send the login as JSON (Content-Type: application/json).'], 400);
    }

    /** Creates a customer account. The page then logs the person in through /api/auth/login. */
    #[Route('/register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $b = $this->body($request);
        $email = strtolower(trim($b['email'] ?? ''));
        $pass = (string) ($b['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->fail('Enter a valid email.', 422);
        }

        $name = trim($b['name'] ?? '');
        if ($name === '' || strlen($name) > 80) {
            $this->fail('Enter your name.', 422);
        }
        if (strlen($pass) < 8) {
            $this->fail('Password must be at least 8 characters.', 422);
        }
        if ($this->users->emailTaken($email)) {
            $this->fail('That email already has an account. Log in instead.', 409);
        }

        $user = (new User())->setName($name)->setEmail($email)->setRole('customer');
        $user->setPassword($this->hasher->hashPassword($user, $pass));
        $this->em->persist($user);
        $this->em->flush();

        return $this->json(['ok' => true]);
    }

    /** Always answers ok, so nobody can find out which emails have an account. */
    #[Route('/forgot', methods: ['POST'])]
    public function forgot(Request $request): JsonResponse
    {
        $email = strtolower(trim($this->body($request)['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->fail('Enter a valid email.', 422);
        }
        $this->resets->request($email);

        return $this->json(['ok' => true]);
    }

    #[Route('/reset', methods: ['POST'])]
    public function reset(Request $request): JsonResponse
    {
        $b = $this->body($request);
        $password = (string) ($b['password'] ?? '');
        if (strlen($password) < 8) {
            $this->fail('Password must be at least 8 characters.', 422);
        }
        if (!$this->resets->reset((string) ($b['token'] ?? ''), $password)) {
            $this->fail('This reset link is invalid or has expired. Request a new one.', 400);
        }

        return $this->json(['ok' => true]);
    }
}
