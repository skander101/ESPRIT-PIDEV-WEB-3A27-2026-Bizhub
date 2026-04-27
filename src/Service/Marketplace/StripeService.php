<?php

namespace App\Service\Marketplace;

use App\Entity\Marketplace\Commande;
use App\Repository\Marketplace\ProduitServiceRepository;
use Psr\Log\LoggerInterface;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use Stripe\Webhook;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// NOTE DEVISE : Stripe ne supporte pas TND (Dinar Tunisien).
// Les montants sont traités en EUR pour le Checkout Stripe.
// 1 TND ≈ 0.30 EUR — en mode test Stripe cette différence n'impacte pas le flux.
// Pour la production, intégrer un convertisseur ou utiliser un prestataire supportant TND.

class StripeService
{
    public function __construct(
        private readonly string $stripeSecretKey,
        private readonly string $stripeWebhookSecret,
        private readonly UrlGeneratorInterface $router,
        private readonly ProduitServiceRepository $produitRepo,
        private readonly LoggerInterface $logger,
    ) {
        Stripe::setApiKey($this->stripeSecretKey);
    }

    /**
     * Crée une Stripe Checkout Session pour une commande confirmée.
     * Remplit commande.stripeSessionId et commande.paymentUrl.
     *
     * @throws \RuntimeException si la commande n'est pas dans le bon statut
     * @throws ApiErrorException  si l'API Stripe renvoie une erreur
     */
    public function createCheckoutSession(Commande $commande): Session
    {
        if ($commande->getStatut() !== Commande::STATUT_CONFIRMEE) {
            throw new \RuntimeException(
                'Le paiement n\'est possible que pour une commande confirmée (commande #' . $commande->getIdCommande() . ').'
            );
        }

        $lineItems = $this->buildLineItems($commande);

        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items'           => $lineItems,
            'mode'                 => 'payment',
            'success_url'          => $this->router->generate(
                'payment_success',
                ['id' => $commande->getIdCommande()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'cancel_url'           => $this->router->generate(
                'payment_cancel',
                ['id' => $commande->getIdCommande()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'metadata'             => [
                'commande_id' => $commande->getIdCommande(),
                'client_id'   => $commande->getIdClient(),
            ],
            'client_reference_id'  => (string) $commande->getIdCommande(),
        ]);

        $this->logger->info('Stripe session créée', [
            'commande_id' => $commande->getIdCommande(),
            'session_id'  => $session->id,
        ]);

        return $session;
    }

    /**
     * Construit les line_items Stripe depuis les lignes de commande.
     * Stripe attend des montants en centimes (entiers).
     */
    private function buildLineItems(Commande $commande): array
    {
        $items = [];

        foreach ($commande->getLignes() as $ligne) {
            $produit = $this->produitRepo->find($ligne->getIdProduit());
            $nom     = $produit ? $produit->getNom() : 'Produit #' . $ligne->getIdProduit();
            // prix TTC unitaire en centimes
            $prixTtcUnitaire = (float) $ligne->getPrixHtUnitaire() * (1 + (float) $ligne->getTvaRate() / 100);
            $montantCentimes = (int) round($prixTtcUnitaire * 100);

            $items[] = [
                'price_data' => [
                    'currency'     => 'eur',
                    'unit_amount'  => $montantCentimes,
                    'product_data' => ['name' => $nom],
                ],
                'quantity' => $ligne->getQuantite(),
            ];
        }

        // Fallback si pas de lignes (commande directe)
        if (empty($items)) {
            $ttc = (float) $commande->getTotalTtc();
            $items[] = [
                'price_data' => [
                    'currency'     => 'eur',
                    'unit_amount'  => (int) round($ttc * 100),
                    'product_data' => ['name' => 'Commande BizHub #' . $commande->getIdCommande()],
                ],
                'quantity' => 1,
            ];
        }

        return $items;
    }

    /**
     * Récupère une session Stripe existante par son ID.
     * Utilisé comme fallback dans la route success pour confirmer le paiement
     * sans attendre le webhook (utile en environnement local/XAMPP).
     *
     * @throws ApiErrorException si l'API Stripe renvoie une erreur
     */
    public function retrieveSession(string $sessionId): Session
    {
        Stripe::setApiKey($this->stripeSecretKey);
        return Session::retrieve([
            'id'     => $sessionId,
            'expand' => ['payment_intent'],
        ]);
    }

    /**
     * Vérifie la signature du webhook Stripe et retourne l'event.
     *
     * @throws \UnexpectedValueException si le payload est invalide
     * @throws \Stripe\Exception\SignatureVerificationException si la signature ne correspond pas
     */
    public function constructWebhookEvent(string $payload, string $sigHeader): \Stripe\Event
    {
        return Webhook::constructEvent($payload, $sigHeader, $this->stripeWebhookSecret);
    }
}
