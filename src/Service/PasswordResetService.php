<?php

namespace App\Service;

use App\Repository\UserRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Twig\Environment;

/**
 * "Forgot password" by email. Only a SHA-256 hash of the token is stored, the link expires after
 * one hour and works once. In Docker the emails land in Mailpit (http://localhost:8025).
 */
class PasswordResetService
{
    public function __construct(
        private Connection $db,
        private UserRepository $users,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private MailerInterface $mailer,
        private Environment $twig,
        private LoggerInterface $logger,
        #[Autowire('%env(APP_URL)%')] private string $appUrl,
        #[Autowire('%env(MAIL_FROM)%')] private string $from,
    ) {
    }

    /** Sends the email if the account exists. Says nothing either way, so nobody can probe which emails have accounts. */
    public function request(string $email): void
    {
        $user = $this->users->findOneByEmail($email);
        if (!$user || !$user->isActive()) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $this->db->executeStatement('DELETE FROM password_resets WHERE user_id = ?', [$user->getId()]);
        $this->db->executeStatement(
            'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))',
            [$user->getId(), hash('sha256', $token)]
        );

        $link = rtrim($this->appUrl, '/') . '/reset-password/' . $token;
        $message = (new Email())
            ->from($this->from)
            ->to($user->getEmail())
            ->subject('Reset your Balay password')
            ->text("Hi {$user->getName()},\n\nUse this link to choose a new password (it works for one hour):\n$link\n\nIf you did not ask for this, ignore this email.")
            ->html($this->twig->render('email/reset_password.html.twig', ['name' => $user->getName(), 'link' => $link]));

        try {
            $this->mailer->send($message);
        } catch (\Throwable $e) {
            $this->logger->error('Password reset email could not be sent: ' . $e->getMessage());
        }
    }

    public function isValid(string $token): bool
    {
        return $this->findValid($token) !== null;
    }

    /** @return bool true when the password was changed */
    public function reset(string $token, string $newPassword): bool
    {
        $row = $this->findValid($token);
        $user = $row ? $this->users->find($row['user_id']) : null;
        if (!$user) {
            return false;
        }

        $user->setPassword($this->hasher->hashPassword($user, $newPassword));
        $this->em->flush();
        $this->db->executeStatement('DELETE FROM password_resets WHERE user_id = ?', [$row['user_id']]);

        return true;
    }

    private function findValid(string $token): ?array
    {
        $row = $this->db->fetchAssociative(
            'SELECT id, user_id FROM password_resets WHERE token_hash = ? AND expires_at > NOW()',
            [hash('sha256', $token)]
        );

        return $row ?: null;
    }
}
