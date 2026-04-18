<?php

namespace App\Service\Investissement;

use App\Entity\Investissement\Deal;
use App\Entity\UsersAvis\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
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
     * Envoie l'email de signature avec un token (lien /front/deal/{id}/signer/{token}).
     * Appelé par DealController comme fallback quand Yousign est indisponible.
     */
    public function sendSignatureEmail(Deal $deal, User $recipient): void
    {
        $token = $deal->getSignature_token();
        if (!$token) {
            // Générer un token si absent
            $token = bin2hex(random_bytes(32));
            $deal->setSignature_token($token);
            $deal->setSignature_token_expires_at((new \DateTime())->modify('+48 hours'));
            $this->em->flush();
        }

        $signatureUrl = $this->urlGenerator->generate(
            'app_deal_sign_token',
            ['id' => $deal->getDeal_id(), 'token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $this->sendSignatureInvitation($deal, $recipient, $signatureUrl);
    }

    /**
     * Envoie l'email d'invitation à signer le contrat.
     */
    public function sendSignatureInvitation(Deal $deal, User $recipient, string $signatureUrl): void
    {
        $subject = sprintf('BizHub — Votre contrat #%d est prêt à être signé', $deal->getDeal_id());

        $body = $this->buildBody($deal, $recipient, $signatureUrl);

        $email = (new Email())
            ->from('noreply@bizhub.tn')
            ->to($recipient->getEmail())
            ->subject($subject)
            ->html($body);

        $this->mailer->send($email);

        // Marquer l'email comme envoyé
        $deal->setEmail_sent(true);
        $this->em->flush();
    }

    /**
     * Envoie une notification de confirmation après signature.
     */
    public function sendSignatureConfirmation(Deal $deal, User $recipient): void
    {
        $subject = sprintf('BizHub — Contrat #%d signé avec succès', $deal->getDeal_id());

        $body = $this->buildConfirmationBody($deal, $recipient);

        $email = (new Email())
            ->from('noreply@bizhub.tn')
            ->to($recipient->getEmail())
            ->subject($subject)
            ->html($body);

        $this->mailer->send($email);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function buildBody(Deal $deal, User $recipient, string $signatureUrl): string
    {
        $amount  = number_format((float) $deal->getAmount(), 2, ',', ' ');
        $dealId  = $deal->getDeal_id();
        $name    = $recipient->getFirstName() ?? $recipient->getEmail();

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><style>
  body { font-family: Arial, sans-serif; background:#f9f9f9; padding:30px; }
  .card { background:#fff; border-radius:12px; padding:30px; max-width:560px; margin:0 auto; }
  h2 { color:#1a1a1a; } .btn { display:inline-block; padding:12px 28px;
  background:#ffbe33; color:#1a1a1a; font-weight:700; border-radius:8px;
  text-decoration:none; margin-top:20px; }
  .footer { color:#888; font-size:11px; margin-top:30px; }
</style></head>
<body><div class="card">
  <h2>Bonjour $name,</h2>
  <p>Votre contrat d'investissement <strong>#$dealId</strong> pour un montant de <strong>$amount TND</strong> est prêt à être signé.</p>
  <p>Cliquez sur le bouton ci-dessous pour signer électroniquement via YouSign :</p>
  <a href="$signatureUrl" class="btn">Signer le contrat</a>
  <p class="footer">BizHub — Si vous n'êtes pas à l'origine de cet investissement, ignorez cet email.</p>
</div></body></html>
HTML;
    }

    private function buildConfirmationBody(Deal $deal, User $recipient): string
    {
        $amount = number_format((float) $deal->getAmount(), 2, ',', ' ');
        $dealId = $deal->getDeal_id();
        $name   = $recipient->getFirstName() ?? $recipient->getEmail();

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><style>
  body { font-family: Arial, sans-serif; background:#f9f9f9; padding:30px; }
  .card { background:#fff; border-radius:12px; padding:30px; max-width:560px; margin:0 auto; }
  h2 { color:#06d6a0; } .footer { color:#888; font-size:11px; margin-top:30px; }
</style></head>
<body><div class="card">
  <h2>Contrat signé !</h2>
  <p>Bonjour $name,</p>
  <p>Le contrat d'investissement <strong>#$dealId</strong> (montant : <strong>$amount TND</strong>) a été signé avec succès.</p>
  <p>Votre partenariat est désormais officiel. Connectez-vous à BizHub pour suivre son évolution.</p>
  <p class="footer">BizHub — L'équipe BizHub</p>
</div></body></html>
HTML;
    }
}
