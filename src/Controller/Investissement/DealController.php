<?php

namespace App\Controller\Investissement;

use App\Entity\Investissement\Deal;
use App\Entity\Investissement\Negotiation;
use App\Entity\UsersAvis\User;
use App\Repository\Investissement\DealRepository;
use App\Repository\NegotiationRepository;
use App\Service\Investissement\ContractPdfService;
use App\Service\Investissement\DealWorkflowService;
use App\Service\Investissement\SignatureEmailService;
use App\Service\Investissement\YousignService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/front/deal')]
class DealController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private DealRepository         $dealRepo,
        private NegotiationRepository  $negotiationRepo,
        private DealWorkflowService    $workflow,
        private ContractPdfService     $pdfService,
        private SignatureEmailService  $signatureEmailService,
        private YousignService         $yousignService,
        private string                 $stripePublicKey,
        private string                 $stripeSecretKey,
    ) {}

    // ────────────────────────────────────────────────────────────────────────
    // VUE PRINCIPALE DU DEAL (stepper workflow)
    // ────────────────────────────────────────────────────────────────────────

    #[Route('/{id}', name: 'app_deal_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(
        #[MapEntity(mapping: ['id' => 'deal_id'])]
        Deal $deal
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $this->assertParticipant($deal, $user->getUserId());

        $negotiation = $this->negotiationRepo->find($deal->getNegotiation_id());
        $buyer  = $this->em->getRepository(User::class)->find($deal->getBuyer_id());
        $seller = $this->em->getRepository(User::class)->find($deal->getSeller_id());

        return $this->render('front/deal/show.html.twig', [
            'deal'            => $deal,
            'negotiation'     => $negotiation,
            'buyer'           => $buyer,
            'seller'          => $seller,
            'can_pay'         => $this->workflow->canPay($deal),
            'can_sign'        => $this->workflow->canSign($deal),
            'can_download'    => $this->workflow->canDownload($deal),
            'stripe_pub_key'  => $this->stripePublicKey,
            'is_buyer'        => $user->getUserId() === $deal->getBuyer_id(),
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // INITIER LE PAIEMENT STRIPE (Checkout)
    // ────────────────────────────────────────────────────────────────────────

    #[Route('/{id}/payer', name: 'app_deal_payer', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function payer(
        #[MapEntity(mapping: ['id' => 'deal_id'])]
        Deal $deal
    ): Response {
        $user = $this->getUser();
        if (!$user || $user->getUserId() !== $deal->getBuyer_id()) {
            throw $this->createAccessDeniedException('Seul l\'investisseur peut initier le paiement.');
        }

        if (!$this->workflow->canPay($deal)) {
            $this->addFlash('error', 'Le paiement n\'est pas disponible à cette étape.');
            return $this->redirectToRoute('app_deal_show', ['id' => $deal->getDeal_id()]);
        }

        // Conversion TND → EUR (1 TND ≈ 0.30 EUR) puis en centimes
        $amountTnd   = (float) $deal->getAmount();
        $amountEur   = $amountTnd * 0.30;
        $amountCents = (int) round($amountEur * 100);
        $amountCents = min($amountCents, 99999999);

        $successUrl = $this->generateUrl('app_deal_stripe_success', ['id' => $deal->getDeal_id()], UrlGeneratorInterface::ABSOLUTE_URL);
        $cancelUrl  = $this->generateUrl('app_deal_stripe_cancel',  ['id' => $deal->getDeal_id()], UrlGeneratorInterface::ABSOLUTE_URL);

        try {
            \Stripe\Stripe::setApiKey($this->stripeSecretKey);

            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items'           => [[
                    'price_data' => [
                        'currency'     => 'eur',
                        'unit_amount'  => $amountCents,
                        'product_data' => [
                            'name'        => sprintf('Investissement BizHub — Deal #%d', $deal->getDeal_id()),
                            'description' => sprintf('Montant : %s TND (~€%s EUR) — Paiement sécurisé via BizHub',
                                number_format($amountTnd, 0, ',', ' '),
                                number_format($amountEur, 2, ',', ' ')
                            ),
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'mode'        => 'payment',
                'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'  => $cancelUrl,
                'metadata'    => ['deal_id' => $deal->getDeal_id()],
            ]);

            return $this->redirect($session->url);

        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur Stripe : ' . $e->getMessage());
            return $this->redirectToRoute('app_deal_show', ['id' => $deal->getDeal_id()]);
        }
    }

    // ────────────────────────────────────────────────────────────────────────
    // RETOUR STRIPE — SUCCÈS
    // ────────────────────────────────────────────────────────────────────────

    #[Route('/{id}/stripe-success', name: 'app_deal_stripe_success', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function stripeSuccess(
        Request $request,
        #[MapEntity(mapping: ['id' => 'deal_id'])]
        Deal $deal
    ): Response {
        $user = $this->getUser();
        if (!$user || $user->getUserId() !== $deal->getBuyer_id()) {
            throw $this->createAccessDeniedException();
        }

        $sessionId = $request->query->get('session_id', '');

        if ($deal->getStatus() === Deal::STATUS_PENDING_PAYMENT && $sessionId) {
            try {
                \Stripe\Stripe::setApiKey($this->stripeSecretKey);
                $session = \Stripe\Checkout\Session::retrieve($sessionId);

                $paymentIntentId = $session->payment_intent ?? '';
                $this->workflow->markAsPaid($deal, $sessionId, $paymentIntentId);

                // Générer le contrat PDF
                $negotiation = $this->negotiationRepo->find($deal->getNegotiation_id());
                $buyer  = $this->em->getRepository(User::class)->find($deal->getBuyer_id());
                $seller = $this->em->getRepository(User::class)->find($deal->getSeller_id());

                if ($negotiation && $buyer && $seller) {
                    $pdfPath = $this->pdfService->generate($deal, $negotiation, $buyer, $seller);
                    $this->workflow->generateContract($deal, $pdfPath);

                    // Envoyer la demande de signature électronique via Yousign
                    try {
                        $this->yousignService->sendSignatureRequest($deal, $buyer);
                        $this->addFlash('success', '✅ Paiement reçu ! Un email de signature électronique (Yousign) a été envoyé à ' . $buyer->getEmail() . '.');
                    } catch (\Exception $yousignEx) {
                        // Yousign failed — fallback to token-based email
                        try {
                            $this->signatureEmailService->sendSignatureEmail($deal, $buyer);
                            $this->addFlash('warning', '✅ Paiement reçu ! (Yousign indisponible — email de signature classique envoyé.)');
                        } catch (\Exception $mailEx) {
                            $this->addFlash('warning', '✅ Paiement reçu ! Contrat généré. Utilisez « Envoyer la signature » depuis la page du deal.');
                        }
                    }
                } else {
                    $this->addFlash('success', '✅ Paiement reçu !');
                }

            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la validation du paiement : ' . $e->getMessage());
            }
        } else {
            $this->addFlash('info', 'Paiement déjà traité.');
        }

        return $this->redirectToRoute('app_deal_show', ['id' => $deal->getDeal_id()]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // RETOUR STRIPE — ANNULATION
    // ────────────────────────────────────────────────────────────────────────

    #[Route('/{id}/stripe-cancel', name: 'app_deal_stripe_cancel', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function stripeCancel(
        #[MapEntity(mapping: ['id' => 'deal_id'])]
        Deal $deal
    ): Response {
        $this->addFlash('warning', 'Paiement annulé. Vous pouvez réessayer quand vous le souhaitez.');
        return $this->redirectToRoute('app_deal_show', ['id' => $deal->getDeal_id()]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // CONFIRMATION DE SIGNATURE VIA LIEN EMAIL (GET = afficher, POST = signer)
    // ────────────────────────────────────────────────────────────────────────

    #[Route('/{id}/signer/{token}', name: 'app_deal_sign_token', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function signByToken(
        Request $request,
        #[MapEntity(mapping: ['id' => 'deal_id'])]
        Deal $deal,
        string $token
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if ($user->getUserId() !== $deal->getBuyer_id()) {
            throw $this->createAccessDeniedException('Ce lien de signature ne vous est pas destiné.');
        }

        $buyer  = $this->em->getRepository(User::class)->find($deal->getBuyer_id());
        $seller = $this->em->getRepository(User::class)->find($deal->getSeller_id());

        // Validate token
        $tokenValid   = true;
        $errorMessage = '';

        if (!$deal->getSignature_token() || $deal->getSignature_token() !== $token) {
            $tokenValid   = false;
            $errorMessage = 'Ce lien de signature est invalide. Il a peut-être déjà été utilisé ou remplacé par un nouveau lien.';
        } elseif (!$deal->getSignature_token_expires_at() || $deal->getSignature_token_expires_at() < new \DateTime()) {
            $tokenValid   = false;
            $errorMessage = 'Ce lien de signature a expiré (validité 48h). Demandez un renvoi depuis la page du deal.';
        } elseif ($deal->getStatus() !== Deal::STATUS_PENDING_SIGNATURE) {
            $tokenValid   = false;
            $errorMessage = 'Ce contrat n\'est plus en attente de signature (statut actuel : ' . $deal->getStatus() . ').';
        }

        // POST — process signing
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('sign_token_' . $deal->getDeal_id(), $request->request->get('_token'))) {
                $this->addFlash('error', 'Token de sécurité invalide.');
                return $this->redirectToRoute('app_deal_show', ['id' => $deal->getDeal_id()]);
            }

            if (!$tokenValid) {
                return $this->render('front/deal/sign_confirm.html.twig', [
                    'deal'          => $deal,
                    'token'         => $token,
                    'token_valid'   => false,
                    'error_message' => $errorMessage,
                    'signed'        => false,
                    'buyer'         => $buyer,
                    'seller'        => $seller,
                ]);
            }

            try {
                $this->workflow->signContractByToken($deal, $token);
                return $this->render('front/deal/sign_confirm.html.twig', [
                    'deal'        => $deal,
                    'token'       => $token,
                    'token_valid' => true,
                    'signed'      => true,
                    'buyer'       => $buyer,
                    'seller'      => $seller,
                ]);
            } catch (\LogicException $e) {
                return $this->render('front/deal/sign_confirm.html.twig', [
                    'deal'          => $deal,
                    'token'         => $token,
                    'token_valid'   => false,
                    'error_message' => $e->getMessage(),
                    'signed'        => false,
                    'buyer'         => $buyer,
                    'seller'        => $seller,
                ]);
            }
        }

        // GET — show confirmation page
        return $this->render('front/deal/sign_confirm.html.twig', [
            'deal'          => $deal,
            'token'         => $token,
            'token_valid'   => $tokenValid,
            'error_message' => $errorMessage,
            'signed'        => false,
            'buyer'         => $buyer,
            'seller'        => $seller,
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // RENVOYER L'EMAIL DE SIGNATURE
    // ────────────────────────────────────────────────────────────────────────

    #[Route('/{id}/resend-signature', name: 'app_deal_resend_signature', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function resendSignature(
        Request $request,
        #[MapEntity(mapping: ['id' => 'deal_id'])]
        Deal $deal
    ): Response {
        $user = $this->getUser();
        if (!$user || $user->getUserId() !== $deal->getBuyer_id()) {
            throw $this->createAccessDeniedException('Seul l\'investisseur peut demander un renvoi de l\'email.');
        }

        if (!$this->isCsrfTokenValid('resend_' . $deal->getDeal_id(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('app_deal_show', ['id' => $deal->getDeal_id()]);
        }

        if ($deal->getStatus() !== Deal::STATUS_PENDING_SIGNATURE) {
            $this->addFlash('error', 'L\'email de signature ne peut être renvoyé qu\'à l\'étape de signature.');
            return $this->redirectToRoute('app_deal_show', ['id' => $deal->getDeal_id()]);
        }

        $buyer = $this->em->getRepository(User::class)->find($deal->getBuyer_id());
        if (!$buyer) {
            $this->addFlash('error', 'Investisseur introuvable.');
            return $this->redirectToRoute('app_deal_show', ['id' => $deal->getDeal_id()]);
        }

        try {
            $this->signatureEmailService->sendSignatureEmail($deal, $buyer);
            $this->addFlash('success', '✅ Email de signature renvoyé ! Vérifiez votre boîte mail (lien valide 48h).');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'envoi de l\'email : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_deal_show', ['id' => $deal->getDeal_id()]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // ENVOYER / RENVOYER VIA YOUSIGN (POST)
    // ────────────────────────────────────────────────────────────────────────

    #[Route('/{id}/yousign-send', name: 'app_deal_yousign_send', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function yousignSend(
        Request $request,
        #[MapEntity(mapping: ['id' => 'deal_id'])]
        Deal $deal
    ): Response {
        $user = $this->getUser();
        if (!$user || $user->getUserId() !== $deal->getBuyer_id()) {
            throw $this->createAccessDeniedException('Seul l\'investisseur peut déclencher la signature.');
        }

        if (!$this->isCsrfTokenValid('yousign_' . $deal->getDeal_id(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('app_deal_show', ['id' => $deal->getDeal_id()]);
        }

        if ($deal->getStatus() !== Deal::STATUS_PENDING_SIGNATURE) {
            $this->addFlash('error', 'La signature n\'est disponible qu\'à l\'étape « En attente de signature ».');
            return $this->redirectToRoute('app_deal_show', ['id' => $deal->getDeal_id()]);
        }

        if (!$deal->getContract_pdf_path()) {
            $this->addFlash('error', 'Le contrat PDF n\'a pas encore été généré.');
            return $this->redirectToRoute('app_deal_show', ['id' => $deal->getDeal_id()]);
        }

        $buyer = $this->em->getRepository(User::class)->find($deal->getBuyer_id());
        if (!$buyer) {
            $this->addFlash('error', 'Investisseur introuvable.');
            return $this->redirectToRoute('app_deal_show', ['id' => $deal->getDeal_id()]);
        }

        try {
            $this->yousignService->sendSignatureRequest($deal, $buyer);
            $this->addFlash('success', '✅ Demande de signature envoyée via Yousign à ' . $buyer->getEmail() . '.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur Yousign : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_deal_show', ['id' => $deal->getDeal_id()]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // WEBHOOK YOUSIGN (POST — appelé par Yousign, pas par l'utilisateur)
    // ────────────────────────────────────────────────────────────────────────

    #[Route('/yousign-webhook', name: 'app_deal_yousign_webhook', methods: ['POST'])]
    public function yousignWebhook(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        // Retrieve the signature_request ID from the payload
        $signatureRequestId = $payload['data']['signature_request']['id']
            ?? $payload['signature_request_id']
            ?? null;

        if (!$signatureRequestId) {
            return $this->json(['error' => 'Missing signature_request id'], 400);
        }

        // Find the matching Deal
        $deal = $this->dealRepo->findOneBy([
            'yousign_signature_request_id' => $signatureRequestId,
        ]);

        if (!$deal) {
            // Not a deal we know — return 200 so Yousign stops retrying
            return $this->json(['ok' => true]);
        }

        $signed = $this->yousignService->handleWebhook($payload, $deal);

        // If Yousign just confirmed the signature, sync the Investment status
        if ($signed) {
            $this->workflow->markAsSignedFromYousign($deal);
        }

        return $this->json(['ok' => true]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // SYNCHRONISER LE STATUT YOUSIGN (bouton manuel — fallback webhook)
    // ────────────────────────────────────────────────────────────────────────

    #[Route('/{id}/yousign-sync', name: 'app_deal_yousign_sync', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function yousignSync(
        Request $request,
        #[MapEntity(mapping: ['id' => 'deal_id'])]
        Deal $deal
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $this->assertParticipant($deal, $user->getUserId());

        if (!$this->isCsrfTokenValid('yousign_sync_' . $deal->getDeal_id(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('app_deal_show', ['id' => $deal->getDeal_id()]);
        }

        if (!$deal->getYousign_signature_request_id()) {
            $this->addFlash('error', 'Aucune demande Yousign associée à ce deal.');
            return $this->redirectToRoute('app_deal_show', ['id' => $deal->getDeal_id()]);
        }

        try {
            $done = $this->yousignService->syncDealStatus($deal);

            if ($done) {
                // Sync investment status
                $this->workflow->markAsSignedFromYousign($deal);

                // Download the signed PDF from Yousign and replace the contract path
                try {
                    $signedPath = $this->yousignService->downloadSignedDocument($deal);
                    if ($signedPath) {
                        $deal->setContract_pdf_path($signedPath);
                        $this->em->flush();
                    }
                } catch (\Throwable) {
                    // Signed PDF unavailable — keep the original unsigned contract
                }

                $this->addFlash('success', '✅ Signature confirmée par Yousign ! Le contrat est maintenant disponible au téléchargement.');
            } else {
                $status = $deal->getYousign_status() ?? 'ongoing';
                $labels = [
                    'ongoing'  => 'en cours — en attente de signature',
                    'declined' => 'refusée par le signataire',
                    'expired'  => 'expirée',
                    'canceled' => 'annulée',
                ];
                $this->addFlash('info', 'Statut Yousign : ' . ($labels[$status] ?? $status) . '. La signature n\'est pas encore finalisée.');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur Yousign : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_deal_show', ['id' => $deal->getDeal_id()]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // TÉLÉCHARGER LE CONTRAT PDF
    // ────────────────────────────────────────────────────────────────────────

    #[Route('/{id}/telecharger', name: 'app_deal_telecharger', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function telecharger(
        #[MapEntity(mapping: ['id' => 'deal_id'])]
        Deal $deal
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $this->assertParticipant($deal, $user->getUserId());

        if (!$this->workflow->canDownload($deal)) {
            $this->addFlash('error', 'Le téléchargement n\'est disponible qu\'après signature du contrat.');
            return $this->redirectToRoute('app_deal_show', ['id' => $deal->getDeal_id()]);
        }

        $publicPath = $deal->getContract_pdf_path();
        $absPath    = $this->getParameter('kernel.project_dir') . '/public' . $publicPath;

        if (!$publicPath || !file_exists($absPath)) {
            $this->addFlash('error', 'Fichier contrat introuvable.');
            return $this->redirectToRoute('app_deal_show', ['id' => $deal->getDeal_id()]);
        }

        $this->workflow->complete($deal);

        $response = new BinaryFileResponse($absPath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            sprintf('contrat-deal-%d.pdf', $deal->getDeal_id())
        );
        $response->headers->set('Content-Type', 'application/pdf');

        return $response;
    }

    // ────────────────────────────────────────────────────────────────────────
    // INTERNAL
    // ────────────────────────────────────────────────────────────────────────

    private function assertParticipant(Deal $deal, int $userId): void
    {
        if ($deal->getBuyer_id() !== $userId && $deal->getSeller_id() !== $userId) {
            throw $this->createAccessDeniedException('Accès refusé à ce deal.');
        }
    }
}
