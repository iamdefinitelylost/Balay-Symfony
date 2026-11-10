<?php

namespace App\Controller;

use App\Security\AppUser;
use Doctrine\DBAL\Connection;
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
    private const ROLES = ['admin', 'staff', 'customer'];
    private const NEW_ROLES = ['admin', 'staff'];

    public function __construct(private Connection $db, private UserPasswordHasherInterface $hasher)
    {
    }

    // ---------- Dashboard ----------

    #[Route('/admin', name: 'app_admin')]
    public function dashboard(): Response
    {
        $one = fn (string $sql) => (int) $this->db->fetchOne($sql);

        return $this->render('admin/dashboard.html.twig', ['stats' => [
            'users' => $one('SELECT COUNT(*) FROM users'),
            'staff' => $one("SELECT COUNT(*) FROM users WHERE role='staff'"),
            'admins' => $one("SELECT COUNT(*) FROM users WHERE role='admin'"),
            'customers' => $one("SELECT COUNT(*) FROM users WHERE role='customer'"),
            'disabled' => $one("SELECT COUNT(*) FROM users WHERE status='disabled'"),
            'records' => $one('SELECT COUNT(*) FROM listings'),
            'bookings' => $one("SELECT COUNT(*) FROM bookings WHERE status='confirmed'"),
            'revenue' => $one("SELECT COALESCE(SUM(total),0) FROM bookings WHERE status='confirmed'"),
        ]]);
    }

    // ---------- Staff / user management (CRUD) ----------

    #[Route('/admin/users', name: 'app_admin_users')]
    public function users(Request $request): Response
    {
        $q = trim($request->query->getString('q'));
        $role = $request->query->getString('role');
        $status = $request->query->getString('status');

        $sql = 'SELECT id, name, email, role, status, created_at FROM users WHERE 1';
        $p = [];
        if ($q !== '') {
            $sql .= ' AND (name LIKE ? OR email LIKE ?)';
            array_push($p, "%$q%", "%$q%");
        }
        if (in_array($role, self::ROLES, true)) {
            $sql .= ' AND role = ?';
            $p[] = $role;
        }
        if (in_array($status, ['active', 'disabled'], true)) {
            $sql .= ' AND status = ?';
            $p[] = $status;
        }

        return $this->render('admin/users.html.twig', [
            'users' => $this->db->fetchAllAssociative($sql . ' ORDER BY created_at DESC, id DESC', $p),
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
                $hash = $this->hasher->hashPassword(new AppUser(0, $form['name'], $form['email'], '', $form['role'], 'active'), $password);
                $this->db->executeStatement(
                    'INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,?)',
                    [$form['name'], $form['email'], $hash, $form['role']]
                );
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
        $form = ['name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']];
        $errors = [];

        if ($request->isMethod('POST')) {
            $this->checkCsrf($request, 'user_form');
            $form = $this->readUserForm($request);
            if ($isSelf) {
                $form['role'] = $user['role']; // you cannot change your own role
            }
            $errors = $this->validateUser($form, $id, self::ROLES);
            if (!$errors) {
                $this->db->executeStatement(
                    'UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?',
                    [$form['name'], $form['email'], $form['role'], $id]
                );
                $this->addFlash('success', 'Account updated.');

                return $this->redirectToRoute('app_admin_users');
            }
        }

        return $this->render('admin/user_form.html.twig', [
            'mode' => 'edit', 'form' => $form, 'errors' => $errors, 'user' => $user, 'roles' => self::ROLES, 'is_self' => $isSelf,
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
        $hash = $this->hasher->hashPassword(new AppUser($id, $user['name'], $user['email'], '', $user['role'], $user['status']), $new);
        $this->db->executeStatement('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, $id]);
        $this->addFlash('success', "Password reset for {$user['name']}. They must log in again.");

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
        $new = $user['status'] === 'active' ? 'disabled' : 'active';
        $this->db->executeStatement('UPDATE users SET status = ? WHERE id = ?', [$new, $id]);
        $this->addFlash('success', "{$user['name']} is now $new.");

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
            $this->db->executeStatement('DELETE FROM users WHERE id = ?', [$id]);
            $this->addFlash('success', "Account {$user['email']} deleted.");

            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/user_delete.html.twig', [
            'user' => $user,
            'bookings' => (int) $this->db->fetchOne('SELECT COUNT(*) FROM bookings WHERE user_id = ?', [$id]),
            'records' => (int) $this->db->fetchOne('SELECT COUNT(*) FROM listings WHERE created_by = ?', [$id]),
        ]);
    }

    // ---------- Bookings ----------

    #[Route('/admin/bookings', name: 'app_admin_bookings')]
    public function bookings(): Response
    {
        return $this->render('admin/bookings.html.twig', ['bookings' => $this->db->fetchAllAssociative(
            'SELECT b.*, l.title FROM bookings b JOIN listings l ON l.id = b.listing_id ORDER BY b.created_at DESC, b.id DESC'
        )]);
    }

    #[Route('/admin/bookings/{id}/cancel', name: 'app_admin_booking_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancelBooking(int $id, Request $request): RedirectResponse
    {
        $this->checkCsrf($request, 'booking_action');
        $this->db->executeStatement("UPDATE bookings SET status='cancelled' WHERE id = ?", [$id]);
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

        return $u instanceof AppUser ? $u->getId() : 0;
    }

    private function findUser(int $id): array
    {
        $u = $this->db->fetchAssociative('SELECT id, name, email, role, status, created_at FROM users WHERE id = ?', [$id]);
        if (!$u) {
            throw $this->createNotFoundException('User not found.');
        }

        return $u;
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
        } elseif ($this->db->fetchOne('SELECT id FROM users WHERE email = ? AND id <> ?', [$f['email'], $ignoreId ?? 0])) {
            $errors[] = 'That email is already used by another account.';
        }
        if (!in_array($f['role'], $allowedRoles, true)) {
            $errors[] = 'Choose a valid role.';
        }

        return $errors;
    }
}
