<?php

namespace App\Service\Auth;

use App\Entity\UsersAvis\User;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;

/**
 * Centralized transactional mail sender for authentication workflows.
 */
class AuthMailerService
{
    public function __construct(
        private MailerInterface $mailer,
        private string $fromEmail,
        private string $fromName,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Sends password reset email with both HTML and text templates.
     */
    public function sendPasswordResetEmail(User $user, string $resetUrl, int $expiresMinutes): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to((string) $user->getEmail())
            ->subject('Reset your BizHub password')
            ->htmlTemplate('emails/auth/password_reset.html.twig')
            ->textTemplate('emails/auth/password_reset.txt.twig')
            ->context([
                'user' => $user,
                'resetUrl' => $resetUrl,
                'expiresMinutes' => $expiresMinutes,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Sends account verification email with expiration info.
     */
    public function sendEmailVerification(User $user, string $verificationUrl, int $expiresMinutes): void
    {
        $recipient = (string) $user->getEmail();

        $this->logger->info('Preparing verification email.', [
            'flow' => 'email_verification',
            'recipient' => $recipient,
            'expires_minutes' => $expiresMinutes,
            'verification_url' => $verificationUrl,
        ]);

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($recipient)
            ->subject('Verify your BizHub email address')
            ->htmlTemplate('emails/auth/email_verification.html.twig')
            ->textTemplate('emails/auth/email_verification.txt.twig')
            ->context([
                'user' => $user,
                'verificationUrl' => $verificationUrl,
                'expiresMinutes' => $expiresMinutes,
            ]);

        try {
            $this->mailer->send($email);
            $this->logger->info('Verification email sent successfully.', [
                'flow' => 'email_verification',
                'recipient' => $recipient,
            ]);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Verification email transport failed.', [
                'flow' => 'email_verification',
                'recipient' => $recipient,
                'error' => $e->getMessage(),
                'exception_class' => $e::class,
            ]);

            throw $e;
        }
    }
}
