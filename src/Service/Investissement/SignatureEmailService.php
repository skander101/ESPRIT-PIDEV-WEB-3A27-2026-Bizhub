<?php

namespace App\Service\Investissement;

use App\Entity\Investissement\Deal;
use App\Entity\UsersAvis\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class SignatureEmailService
{
    public function __construct(
        private MailerInterface        $mailer,
        private Environment            $twig,
        private EntityManagerInterface $em,
        private UrlGeneratorInterface  $urlGenerator,
    ) {}

    /**
     * Generate a new signature token, persist it, then send the email.
     */
    public function sendSignatureEmail(Deal $deal, User $buyer): void
    {
        // Generate secure 64-char token
        $token     = bin2hex(random_bytes(32));
        $expiresAt = new \DateTime('+48 hours');

        $deal->setSignature_token($token);
        $deal->setSignature_token_expires_at($expiresAt);
        $deal->setSignature_sent_at(new \DateTime());
        $this->em->flush();

        $signingUrl = $this->urlGenerator->generate(
            'app_deal_sign_token',
            ['id' => $deal->getDeal_id(), 'token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $html = $this->twig->render('emails/deal_signature.html.twig', [
            'deal'        => $deal,
            'buyer'       => $buyer,
            'signing_url' => $signingUrl,
            'expires_at'  => $expiresAt,
        ]);

        $email = (new Email())
            ->from(new Address('noreply@bizhub.tn', 'BizHub'))
            ->to(new Address($buyer->getEmail(), $buyer->getFullName()))
            ->subject(sprintf('BizHub — Signez votre contrat d\'investissement #%d', $deal->getDeal_id()))
            ->html($html);

        $this->mailer->send($email);
    }
}
