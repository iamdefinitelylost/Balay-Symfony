<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\ListingRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin area. Protected three ways:
 *  1. security.yaml  access_control: ^/admin -> ROLE_ADMIN
 *  2. this controller-level #[IsGranted('ROLE_ADMIN')]
 *  3. the Twig menu only shows these links when is_granted('ROLE_ADMIN')
 */
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    private const NEW_ROLES = ['admin', 'staff'];

    public function __construct(
        private UserRepository $users,
        private ListingRepository $listings,
        private BookingRepository $bookings,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
    ) {
    }

    // ---------- Dashboard ----------

    #[Route('/admin', name: 'app_admin')]
    public function dashboard(): Response
    {
        return $this->render('admin/dashboard.html.twig', ['stats' => [
            'users' => $this->users->count([]),
            'staff' => $this->users->count(['role' => 'staff']),
            'admins' => $this->users->count(['role' => 'admin']),
            'customers' => $this->users->count(['role' => 'customer']),
            'disabled' => $this->users->count(['status' => 'disabled']),
            'records' => $this->listings->count([]),
            'bookings' => $this->bookings->count(['status' => 'confirmed']),
            'revenue' => $this->bookings->revenue(),
        ]]);
    }

    // ---------- Staff / user management (CRUD) ----------

    #[Route('/admin/users', name: 'app_admin_users')]
    public function users(Request $request): Response
    {
        $q = trim($request->query->getString('q'));
        $role = $request->query->getString('role');
        $status = $request->query->getString('status');

        return $this->render('admin/users.html.twig', [
            'users' => $this->users->search($q, $role, $status),
            'q' => $q, 'role' => $role, 'status' => $status,
        ]);
    }

    #[Route('/admin/users/new', name: 'app_admin_user_new', methods: ['GET', 'POST'])]
    public function newUser(Request $request): Response
    {
        $form = ['name' => '', 'email' => '', 'role' => 'staff'];
        $errors = [];

        if ($request->isMethod('POST')) {
            $this->checkCsrf($request, 'user_form');
            $form = $this->readUserForm($request);
            $password = $request->request->getString('password');
            $errors = $this->validateUser($form, null, self::NEW_ROLES);
            if (strlen($password) < 8) {
                $errors[] = 'Password must be at least 8 characters.';
            }
            if (!$errors) {
                $user = (new User())->setName($form['name'])->setEmail($form['email'])->setRole($form['role']);
                $user->setPassword($this->hasher->hashPassword($user, $password));
                $this->em->persist($user);
                $this->em->flush();
                $this->addFlash('success', "Account created for {$form['name']} ({$form['role']}).");

                return $this->redirectToRoute('app_admin_users');
            }
        }

        return $this->render('admin/user_form.html.twig', [
            'mode' => 'new', 'form' => $form, 'errors' => $errors, 'user' => null, 'roles' => self::NEW_ROLES, 'is_self' => false,
        ], new Response('', $errors ? 422 : 200));
    }

    #[Route('/admin/users/{id}/edit', name: 'app_admin_user_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editUser(int $id, Request $request): Response
    {
        $user = $this->findUser($id);
        $isSelf = $this->currentId() === $id;
        $form = ['name' => $user->getName(), 'email' => $user->getEmail(), 'role' => $user->getRole()];
        $errors = [];

        if ($request->isMethod('POST')) {
            $this->checkCsrf($request, 'user_form');
            $form = $this->readUserForm($request);
            if ($isSelf) {
                $form['role'] = $user->getRole(); // you cannot change your own role
            }
            $errors = $this->validateUser($form, $id, User::ROLES);
            if (!$errors) {
                $user->setName($form['name'])->setEmail($form['email'])->setRole($form['role']);
                $this->em->flush();
                $this->addFlash('success', 'Account updated.');

                return $this->redirectToRoute('app_admin_users');
            }
        }

        return $this->render('admin/user_form.html.twig', [
            'mode' => 'edit', 'form' => $form, 'errors' => $errors, 'user' => $user, 'roles' => User::ROLES, 'is_self' => $isSelf,
        ], new Response('', $errors ? 422 : 200));
    }

    #[Route('/admin/users/{id}/reset-password', name: 'app_admin_user_reset', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function resetPassword(int $id, Request $request): RedirectResponse
    {
        $this->checkCsrf($request, 'user_action');
        $user = $this->findUser($id);
        $new = $request->request->getString('new_password');
        if (strlen($new) < 8) {
            $this->addFlash('error', 'The new password must be at least 8 characters.');

            return $this->redirectToRoute('app_admin_user_edit', ['id' => $id]);
        }
        $user->setPassword($this->hasher->hashPassword($user, $new));
        $this->em->flush();
        $this->addFlash('success', "Password reset for {$user->getName()}. They must log in again.");

        return $this->redirectToRoute('app_admin_users');
    }

    /** Disable (or re-enable) an account. A disabled person is logged out on their next request. */
    #[Route('/admin/users/{id}/status', name: 'app_admin_user_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggleStatus(int $id, Request $request): RedirectResponse
    {
        $this->checkCsrf($request, 'user_action');
        $user = $this->findUser($id);
        if ($this->currentId() === $id) {
            $this->addFlash('error', 'You cannot disable your own account.');

            return $this->redirectToRoute('app_admin_users');
        }
        $user->setStatus($user->isActive() ? 'disabled' : 'active');
        $this->em->flush();
        $this->addFlash('success', "{$user->getName()} is now {$user->getStatus()}.");

        return $this->redirectToRoute('app_admin_users');
    }

    /** GET shows the confirmation page, POST does the delete. */
    #[Route('/admin/users/{id}/delete', name: 'app_admin_user_delete', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function deleteUser(int $id, Request $request): Response
    {
        $user = $this->findUser($id);
        if ($this->currentId() === $id) {
            $this->addFlash('error', 'You cannot delete your own account.');

            return $this->redirectToRoute('app_admin_users');
        }
        if ($request->isMethod('POST')) {
            $this->checkCsrf($request, 'user_action');
            $email = $user->getEmail();
            $this->em->remove($user); // the database cascades their bookings and detaches their records
            $this->em->flush();
            $this->addFlash('success', "Account $email deleted.");

            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/user_delete.html.twig', [
            'user' => $user,
            'bookings' => $this->bookings->count(['user' => $user]),
            'records' => $this->listings->count(['createdBy' => $user]),
        ]);
    }

    // ---------- Bookings ----------

    #[Route('/admin/bookings', name: 'app_admin_bookings')]
    public function bookings(): Response
    {
        return $this->render('admin/bookings.html.twig', ['bookings' => $this->bookings->findAllWithListing()]);
    }

    #[Route('/admin/bookings/{id}/cancel', name: 'app_admin_booking_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancelBooking(int $id, Request $request): RedirectResponse
    {
        $this->checkCsrf($request, 'booking_action');
        $booking = $this->bookings->find($id) ?? throw $this->createNotFoundException('Booking not found.');
        $booking->setStatus('cancelled');
        $this->em->flush();
        $this->addFlash('success', 'Booking cancelled.');

        return $this->redirectToRoute('app_admin_bookings');
    }

    // ---------- helpers ----------

    private function checkCsrf(Request $request, string $tokenId): void
    {
        if (!$this->isCsrfTokenValid($tokenId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    private function currentId(): int
    {
        $u = $this->getUser();

        return $u instanceof User ? (int) $u->getId() : 0;
    }

    private function findUser(int $id): User
    {
        return $this->users->find($id) ?? throw $this->createNotFoundException('User not found.');
    }

    private function readUserForm(Request $request): array
    {
        return [
            'name' => trim($request->request->getString('name')),
            'email' => strtolower(trim($request->request->getString('email'))),
            'role' => $request->request->getString('role'),
        ];
    }

    /** @param string[] $allowedRoles */
    private function validateUser(array $f, ?int $ignoreId, array $allowedRoles): array
    {
        $errors = [];
        if ($f['name'] === '' || mb_strlen($f['name']) > 80) {
            $errors[] = 'Enter a name (up to 80 characters).';
        }
        if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL) || strlen($f['email']) > 120) {
            $errors[] = 'Enter a valid email.';
        } elseif ($this->users->emailTaken($f['email'], $ignoreId)) {
            $errors[] = 'That email is already used by another account.';
        }
        if (!in_array($f['role'], $allowedRoles, true)) {
            $errors[] = 'Choose a valid role.';
        }

        return $errors;
    }
}
