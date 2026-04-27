<?php

declare(strict_types=1);

namespace App\Service\Elearning;

use App\Entity\Elearning\Formation;
use App\Entity\Elearning\Participation;
use App\Entity\Elearning\PromoCode;
use App\Entity\UsersAvis\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ParticipationConfirmationMailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $fromEmail,
        private readonly string $fromName,
    ) {
    }

    public function send(Participation $participation, string $absolutePdfPathOnDisk, ?PromoCode $giftPromo = null): void
    {
        $user = $participation->getUser();
        $formation = $participation->getFormation();
        if (!$user instanceof User || !$user->getEmail() || !$formation instanceof Formation) {
            throw new \InvalidArgumentException('Impossible d\'envoyer l\'email : données incomplètes.');
        }

        $to = new Address($user->getEmail(), (string) ($user->getFullName() ?? ''));
        $subject = 'Confirmation de participation — ' . ($formation->getTitle() ?? 'Formation');

        $formationsUrl = $this->urlGenerator->generate('app_front_formations_index', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($to)
            ->subject($subject)
            ->htmlTemplate('emails/elearning/participation_confirmation.html.twig')
            ->context([
                'user' => $user,
                'formation' => $formation,
                'participation' => $participation,
                'formations_url' => $formationsUrl,
                'transaction_id' => $participation->getTransactionId(),
                'gift_promo' => $giftPromo,
            ]);

        if (is_file($absolutePdfPathOnDisk)) {
            $email->attachFromPath($absolutePdfPathOnDisk, basename($absolutePdfPathOnDisk), 'application/pdf');
        }

        $this->mailer->send($email);
    }
}
