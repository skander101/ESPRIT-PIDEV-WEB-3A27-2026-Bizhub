<?php

namespace App\Service\Marketplace;

use App\Entity\Marketplace\Commande;
use App\Entity\UsersAvis\User;
use Psr\Log\LoggerInterface;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;

/**
 * Envoi de notifications WhatsApp via Twilio.
 *
 * Twilio WhatsApp Sandbox (test) :
 *   FROM = whatsapp:+14155238886
 *   TO   = whatsapp:+{numero_du_destinataire}
 *   Le destinataire doit avoir rejoint le sandbox : https://wa.me/14155238886?text=join+<sandbox_code>
 *
 * Production (WhatsApp Business API) :
 *   FROM = whatsapp:{votre_numero_approuve_meta}
 *   Les messages initiaux doivent utiliser un template approuvé par Meta (ContentSid).
 *   Les réponses dans la fenêtre 24h peuvent utiliser un message libre.
 */
class TwilioService
{
    private Client $client;

    public function __construct(
        private readonly string          $twilioAccountSid,
        private readonly string          $twilioAuthToken,
        private readonly string          $twilioWhatsappFrom, // ex: +14155238886 (sans préfixe whatsapp:)
        private readonly LoggerInterface $logger,
    ) {
        $this->client = new Client($this->twilioAccountSid, $this->twilioAuthToken);
    }

    // ════════════════════════════════════════════════════════════════════
    //  NOTIFICATION WHATSAPP — COMMANDE CONFIRMÉE
    // ════════════════════════════════════════════════════════════════════

    /**
     * Envoie un message WhatsApp à la startup pour l'informer qu'une commande a été confirmée.
     *
     * Renommé sendConfirmationSms → sendConfirmationWhatsApp mais l'ancien nom est gardé
     * en alias pour ne pas casser CommandeConfirmeeListener.
     */
    public function sendConfirmationSms(Commande $commande, User $startup, User $investisseur): void
    {
        $this->sendConfirmationWhatsApp($commande, $startup, $investisseur);
    }

    public function sendConfirmationWhatsApp(Commande $commande, User $startup, User $investisseur): void
    {
        $phone = $startup->getPhone();

        if (empty($phone)) {
            $this->logger->warning('WhatsApp non envoyé : numéro de téléphone absent', [
                'startup_id'  => $startup->getUserId(),
                'commande_id' => $commande->getIdCommande(),
            ]);
            return;
        }

        // ── Normalisation du numéro ──────────────────────────────────────
        // Le numéro stocké en base peut être +21698..., 21698..., ou 698...
        // On s'assure d'avoir le format E.164 sans préfixe whatsapp:
        $phoneNormalized = $this->normalizePhone($phone);

        $from = 'whatsapp:' . $this->twilioWhatsappFrom;
        $to   = 'whatsapp:' . $phoneNormalized;

        $body = $this->buildWhatsAppMessage($commande, $investisseur);

        $this->logger->info('Tentative envoi WhatsApp Twilio', [
            'from'        => $from,
            'to'          => $to,
            'commande_id' => $commande->getIdCommande(),
        ]);

        try {
            $message = $this->client->messages->create($to, [
                'from' => $from,
                'body' => $body,
            ]);

            $this->logger->info('WhatsApp envoyé avec succès via Twilio', [
                'commande_id' => $commande->getIdCommande(),
                'message_sid' => $message->sid,
                'status'      => $message->status,
                'to'          => $to,
            ]);

        } catch (TwilioException $e) {
            // On log sans faire planter le flux principal
            $this->logger->error('Échec envoi WhatsApp Twilio', [
                'commande_id' => $commande->getIdCommande(),
                'to'          => $to,
                'error_code'  => $e->getCode(),
                'error'       => $e->getMessage(),
                'hint'        => 'Vérifiez que le numéro a rejoint le sandbox Twilio WhatsApp.',
            ]);
        }
    }

    // ════════════════════════════════════════════════════════════════════
    //  PRIVATE HELPERS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Construit le message WhatsApp (message libre, fenêtre 24h ou sandbox).
     * En production avec template approuvé, utiliser ContentSid + ContentVariables.
     */
    private function buildWhatsAppMessage(Commande $commande, User $investisseur): string
    {
        $investisseurName = $investisseur->getFullName() ?? $investisseur->getEmail();
        $montant          = number_format((float) $commande->getTotalTtc(), 3, '.', ' ');

        return implode("\n", [
            '🎉 *BizHub — Commande confirmée !*',
            '',
            '📦 Commande n° *#' . $commande->getIdCommande() . '*',
            '✅ Confirmée par : ' . $investisseurName,
            '💰 Montant TTC   : *' . $montant . ' TND*',
            '',
            'Connectez-vous sur BizHub pour procéder au paiement et suivre votre commande.',
            '👉 http://localhost/ESPRIT-PIDEV-WEB-3A27-2026-Bizhub-main/marketplace/commandes/' . $commande->getIdCommande(),
        ]);
    }

    /**
     * Normalise un numéro de téléphone vers le format E.164 (+XXXXXXXXXXX).
     * Si le numéro ne commence pas par +, on préfixe +216 (Tunisie par défaut).
     */
    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\s+/', '', $phone); // supprime espaces
        if (str_starts_with($phone, '+')) {
            return $phone;
        }
        if (str_starts_with($phone, '00')) {
            return '+' . substr($phone, 2);
        }
        // Numéro local tunisien (8 chiffres)
        return '+216' . ltrim($phone, '0');
    }
}
