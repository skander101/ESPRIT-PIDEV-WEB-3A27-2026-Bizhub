<?php

namespace App\Controller\Marketplace;

use App\Entity\Marketplace\Commande;
use App\Entity\UsersAvis\User;
use App\Entity\Marketplace\CommandeStatusHistory;
use App\Repository\Marketplace\CommandeRepository;
use App\Service\Marketplace\FactureService;
use App\Service\Marketplace\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/marketplace/paiement', name: 'payment_')]
class PaymentController extends AbstractController
{
    // ════════════════════════════════════════════════════════════════════
    //  INITIER LE PAIEMENT — startup clique sur "Payer"
    // ════════════════════════════════════════════════════════════════════

    #[Route('/initier/{id}', name: 'initier', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function initier(
        Commande               $commande,
        Request                $request,
        StripeService          $stripeService,
        EntityManagerInterface $em,
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if ($commande->getIdClient() !== (int) ($user instanceof User ? $user->getUserId() : null)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('payer_' . $commande->getIdCommande(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token de sécurité invalide.');
            return $this->redirectToRoute('commande_show', ['id' => $commande->getIdCommande()]);
        }

        if ($commande->getStatut() !== Commande::STATUT_CONFIRMEE) {
            $this->addFlash('warning', 'Le paiement n\'est possible que pour une commande confirmée.');
            return $this->redirectToRoute('commande_show', ['id' => $commande->getIdCommande()]);
        }

        if ($commande->isEstPayee()) {
            $this->addFlash('info', 'Cette commande est déjà payée.');
            return $this->redirectToRoute('commande_show', ['id' => $commande->getIdCommande()]);
        }

        try {
            $session = $stripeService->createCheckoutSession($commande);

            $commande
                ->setStripeSessionId($session->id)
                ->setPaymentUrl($session->url)
                ->setPaymentStatus('en cours')
                ->setStatut(Commande::STATUT_EN_COURS_PAIEMENT);

            $history = (new CommandeStatusHistory())
                ->setCommande($commande)
                ->setStatutPrecedent(Commande::STATUT_CONFIRMEE)
                ->setStatutNouveau(Commande::STATUT_EN_COURS_PAIEMENT)
                ->setChangedByUserId((int) ($user instanceof User ? $user->getUserId() : null))
                ->setNote('Session Stripe créée : ' . $session->id);

            $em->persist($history);
            $em->flush();

            // Redirection vers Stripe Checkout
            return $this->redirect($session->url);

        } catch (ApiErrorException $e) {
            $this->addFlash('danger', 'Erreur Stripe : ' . $e->getMessage());
            return $this->redirectToRoute('commande_show', ['id' => $commande->getIdCommande()]);
        }
    }

    // ════════════════════════════════════════════════════════════════════
    //  SUCCÈS — Stripe redirige ici après paiement réussi
    //
    //  IMPORTANT (environnement local / XAMPP) :
    //  Le webhook Stripe ne peut pas atteindre localhost directement.
    //  On récupère donc la session Stripe ici comme FALLBACK pour marquer
    //  la commande payée même si le webhook n'a pas encore été reçu.
    //  En production, le webhook reste la méthode principale.
    // ════════════════════════════════════════════════════════════════════

    #[Route('/succes/{id}', name: 'success', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function success(
        Commande               $commande,
        StripeService          $stripeService,
        FactureService         $factureService,
        EntityManagerInterface $em,
        LoggerInterface        $logger,
    ): Response {
        if (!$commande->isEstPayee() && $commande->getStripeSessionId()) {
            $verified = false;
            $sessionId = $commande->getStripeSessionId();
            $paymentIntentId = null;

            try {
                $session = $stripeService->retrieveSession($sessionId);

                if ($session->payment_status === 'paid') {
                    $verified = true;
                    $paymentIntentId = $session->payment_intent?->id;
                }
            } catch (\Throwable $e) {
                $logger->warning('success: retrieveSession a échoué, fallback par redirection', [
                    'error'       => $e->getMessage(),
                    'commande_id' => $commande->getIdCommande(),
                    'session_id'  => $sessionId,
                ]);
            }

            // Stripe ne redirige vers success_url qu'après paiement réussi
            // Si la commande est toujours en cours de paiement, on considère
            // la redirection comme confirmation
            if (!$verified && $commande->getStatut() === Commande::STATUT_EN_COURS_PAIEMENT) {
                $logger->info('success: confirmation par redirection Stripe', [
                    'commande_id' => $commande->getIdCommande(),
                    'session_id'  => $sessionId,
                ]);
                $verified = true;
            }

            if ($verified) {
                $this->applyPaymentSuccess($commande, $sessionId, $paymentIntentId, $em);
                $factureService->createFromCommande($commande);
            }
        } elseif ($commande->isEstPayee()) {
            $factureService->createFromCommande($commande);
        }

        return $this->render('front/Marketplace/payment/success.html.twig', [
            'commande' => $commande,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  ANNULATION
    // ════════════════════════════════════════════════════════════════════

    #[Route('/annulation/{id}', name: 'cancel', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function cancel(Commande $commande, EntityManagerInterface $em): Response
    {
        if ($commande->getStatut() === Commande::STATUT_EN_COURS_PAIEMENT) {
            $commande
                ->setStatut(Commande::STATUT_CONFIRMEE)
                ->setPaymentStatus('échoué');
            $em->flush();
        }

        $this->addFlash('warning', 'Paiement annulé. Vous pouvez réessayer depuis la fiche commande.');
        return $this->redirectToRoute('commande_show', ['id' => $commande->getIdCommande()]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  WEBHOOK STRIPE
    //
    //  Pour tester en local avec XAMPP, utiliser Stripe CLI :
    //    stripe listen --forward-to localhost/ESPRIT-PIDEV.../marketplace/paiement/webhook
    //  Cela vous donnera un webhook secret temporaire (whsec_...) à mettre dans .env
    // ════════════════════════════════════════════════════════════════════

    #[Route('/webhook', name: 'webhook', methods: ['POST'])]
    public function webhook(
        Request                $request,
        StripeService          $stripeService,
        FactureService         $factureService,
        CommandeRepository     $commandeRepo,
        EntityManagerInterface $em,
        LoggerInterface        $logger,
    ): JsonResponse {
        $payload   = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature', '');

        try {
            $event = $stripeService->constructWebhookEvent($payload, $sigHeader);
        } catch (\Throwable $e) {
            $logger->error('Webhook Stripe invalide : ' . $e->getMessage());
            return new JsonResponse(['error' => 'Webhook invalide.'], Response::HTTP_BAD_REQUEST);
        }

        match ($event->type) {
            'checkout.session.completed'    => $this->handleCheckoutCompleted(
                $event->data->object, $commandeRepo, $em, $factureService, $logger
            ),
            'payment_intent.payment_failed' => $this->handlePaymentFailed(
                $event->data->object, $commandeRepo, $em, $logger
            ),
            default => $logger->info('Webhook Stripe non géré : ' . $event->type),
        };

        return new JsonResponse(['status' => 'ok']);
    }

    // ── Helpers privés ───────────────────────────────────────────────────

    /**
     * Marque la commande comme payée et crée la facture.
     * Appelé depuis webhook ET depuis success() (fallback).
     */
    private function handleCheckoutCompleted(
        object                 $session,
        CommandeRepository     $commandeRepo,
        EntityManagerInterface $em,
        FactureService         $factureService,
        LoggerInterface        $logger,
    ): void {
        $commandeId = (int) ($session->metadata['commande_id'] ?? 0);
        $commande   = $commandeRepo->find($commandeId);

        if (!$commande) {
            $logger->error('Webhook checkout.completed: commande introuvable', ['commande_id' => $commandeId]);
            return;
        }

        // Idempotence : ne pas retraiter si déjà payée
        if ($commande->isEstPayee()) {
            $logger->info('Webhook: commande déjà payée, ignoré', ['commande_id' => $commandeId]);
            // Garantir que la facture existe malgré tout
            $factureService->createFromCommande($commande);
            return;
        }

        $this->applyPaymentSuccess(
            $commande,
            $session->id,
            $session->payment_intent ?? null,
            $em
        );

        // Créer la facture automatiquement
        $facture = $factureService->createFromCommande($commande);

        $logger->info('Commande payée + facture créée via webhook Stripe', [
            'commande_id'    => $commandeId,
            'payment_intent' => $session->payment_intent,
            'facture'        => $facture->getNumeroFacture(),
        ]);
    }

    /**
     * Applique le changement d'état "payé" sur la commande + historique.
     * Méthode partagée entre webhook et fallback success.
     */
    private function applyPaymentSuccess(
        Commande               $commande,
        string                 $sessionId,
        ?string                $paymentIntentId,
        EntityManagerInterface $em,
    ): void {
        $commande
            ->setEstPayee(true)
            ->setPaymentStatus('complété')
            ->setStatut(Commande::STATUT_PAYEE)
            ->setPaymentRef($sessionId)
            ->setStripePaymentIntentId($paymentIntentId);

        $ref = new \ReflectionProperty(Commande::class, 'paidAt');
        $ref->setValue($commande, new \DateTime());

        $history = (new CommandeStatusHistory())
            ->setCommande($commande)
            ->setStatutPrecedent(Commande::STATUT_EN_COURS_PAIEMENT)
            ->setStatutNouveau(Commande::STATUT_PAYEE)
            ->setNote('Paiement Stripe confirmé. Session : ' . $sessionId
                    . ($paymentIntentId ? ' · PaymentIntent : ' . $paymentIntentId : ''));

        $em->persist($history);
        $em->flush();
    }

    private function handlePaymentFailed(
        object                 $paymentIntent,
        CommandeRepository     $commandeRepo,
        EntityManagerInterface $em,
        LoggerInterface        $logger,
    ): void {
        $commande = $commandeRepo->findOneBy(['stripePaymentIntentId' => $paymentIntent->id]);
        if (!$commande) {
            return;
        }

        // Guard: never degrade a commande that is already confirmed paid
        if ($commande->isEstPayee()) {
            $logger->warning('handlePaymentFailed: commande déjà payée — événement ignoré pour éviter incohérence', [
                'commande_id'    => $commande->getIdCommande(),
                'payment_intent' => $paymentIntent->id,
            ]);
            return;
        }

        $commande
            ->setPaymentStatus('échoué')
            ->setStatut(Commande::STATUT_CONFIRMEE);

        $history = (new CommandeStatusHistory())
            ->setCommande($commande)
            ->setStatutPrecedent(Commande::STATUT_EN_COURS_PAIEMENT)
            ->setStatutNouveau(Commande::STATUT_CONFIRMEE)
            ->setNote('Paiement échoué : ' . ($paymentIntent->last_payment_error->message ?? 'Erreur inconnue'));

        $em->persist($history);
        $em->flush();

        $logger->warning('Paiement Stripe échoué', ['payment_intent' => $paymentIntent->id]);
    }
}
