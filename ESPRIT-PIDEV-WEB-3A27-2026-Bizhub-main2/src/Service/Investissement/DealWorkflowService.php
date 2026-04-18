<?php

namespace App\Service\Investissement;

use App\Entity\Investissement\Deal;
use App\Entity\Investissement\Investment;
use App\Entity\Investissement\Negotiation;
use App\Entity\UsersAvis\User;
use App\Repository\Investissement\DealRepository;
use App\Repository\InvestmentRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Centralises all business-logic transitions for the investment workflow:
 *
 * Investment (en_attente)
 *   ↓ startNegotiation()  →  Negotiation open   / Investment en_negociation
 *   ↓ acceptDeal()        →  Deal pending_payment / Investment accepte
 *   ↓ markAsPaid()        →  Deal paid
 *   ↓ generateContract()  →  Deal pending_signature / Investment contrat_genere
 *   ↓ sendSignature()     →  email/Yousign dispatched
 *   ↓ confirmSignature()  →  Deal signed / Investment signe
 *   ↓ complete()          →  Deal completed / Investment termine
 */
class DealWorkflowService
{
    public function __construct(
        private EntityManagerInterface $em,
        private DealRepository         $dealRepository,
        private InvestmentRepository   $investmentRepo,
    ) {}

    // ── Negotiation → Deal creation ───────────────────────────────────────────

    /**
     * Accept a negotiation and create the corresponding Deal.
     * Updates the linked Investment statut → 'accepte'.
     */
    public function acceptDeal(Negotiation $negotiation): Deal
    {
        $deal = $this->createDealFromNegotiation($negotiation);
        $this->syncInvestmentByDeal($deal, 'accepte');
        return $deal;
    }

    /**
     * Create a Deal from an accepted Negotiation.
     * Low-level method — prefer acceptDeal() for full workflow orchestration.
     */
    public function createDealFromNegotiation(Negotiation $negotiation): Deal
    {
        $deal = new Deal();
        $deal->setNegotiation_id($negotiation->getNegotiation_id());
        $deal->setProject_id($negotiation->getProject()->getProject_id());
        $deal->setBuyer_id($negotiation->getInvestor()->getUserId());
        $deal->setSeller_id($negotiation->getStartup()->getUserId());
        $deal->setAmount($negotiation->getFinal_amount() ?? $negotiation->getProposed_amount() ?? 0);
        $deal->setStatus(Deal::STATUS_PENDING_PAYMENT);
        $deal->setCreated_at(new \DateTime());

        $this->em->persist($deal);
        $this->em->flush();

        return $deal;
    }

    // ── Payment ───────────────────────────────────────────────────────────────

    /**
     * Mark deal as paid after successful Stripe payment.
     */
    public function markAsPaid(Deal $deal, string $sessionId, string $paymentIntentId): void
    {
        $this->assertStatus($deal, Deal::STATUS_PENDING_PAYMENT, 'Le paiement n\'est pas autorisé à cette étape.');

        $deal->setStatus(Deal::STATUS_PAID);
        $deal->setStripe_checkout_session_id($sessionId);
        $deal->setStripe_payment_intent_id($paymentIntentId);
        $deal->setStripe_payment_status('paid');
        $this->em->flush();
    }

    // ── Contract ──────────────────────────────────────────────────────────────

    /**
     * Generate and store the contract PDF, then move to pending_signature.
     * Updates Investment statut → 'contrat_genere'.
     */
    public function generateContract(Deal $deal, string $pdfPath): void
    {
        $this->assertStatus($deal, Deal::STATUS_PAID, 'Le contrat ne peut être généré qu\'après paiement.');

        $deal->setContract_pdf_path($pdfPath);
        $deal->setStatus(Deal::STATUS_PENDING_SIGNATURE);
        $this->em->flush();

        $this->syncInvestmentByDeal($deal, 'contrat_genere');
    }

    // ── Signature ─────────────────────────────────────────────────────────────

    /**
     * Send the signature email (generate token + dispatch).
     * Delegates to SignatureEmailService — kept here for workflow completeness.
     */
    public function sendSignature(Deal $deal, User $buyer, SignatureEmailService $emailService): void
    {
        if ($deal->getStatus() !== Deal::STATUS_PENDING_SIGNATURE) {
            throw new \LogicException('La signature n\'est disponible qu\'à l\'étape « En attente de signature ».');
        }
        $emailService->sendSignatureEmail($deal, $buyer);
    }

    /**
     * Validate the email token and sign the contract.
     * Updates Investment statut → 'signe'.
     */
    public function confirmSignature(Deal $deal, string $token): void
    {
        $this->signContractByToken($deal, $token);
    }

    /**
     * Validate the email token and sign the contract.
     * Clears the token after successful signing.
     * Updates Investment statut → 'signe'.
     */
    public function signContractByToken(Deal $deal, string $token): void
    {
        if (!$deal->getSignature_token() || $deal->getSignature_token() !== $token) {
            throw new \LogicException('Token de signature invalide.');
        }

        if (!$deal->getSignature_token_expires_at() || $deal->getSignature_token_expires_at() < new \DateTime()) {
            throw new \LogicException('Le lien de signature a expiré. Demandez un renvoi depuis votre espace BizHub.');
        }

        $this->assertStatus($deal, Deal::STATUS_PENDING_SIGNATURE, 'La signature n\'est pas autorisée à cette étape.');

        $deal->setStatus(Deal::STATUS_SIGNED);
        $deal->setCompleted_at(new \DateTime());
        $deal->setSignature_token(null);
        $deal->setSignature_token_expires_at(null);
        $this->em->flush();

        $this->syncInvestmentByDeal($deal, 'signe');
    }

    /**
     * Mark deal as signed (internal — no token validation).
     * Prefer signContractByToken() for web flows.
     * Updates Investment statut → 'signe'.
     */
    public function signContract(Deal $deal): void
    {
        $this->assertStatus($deal, Deal::STATUS_PENDING_SIGNATURE, 'La signature n\'est pas autorisée à cette étape.');

        $deal->setStatus(Deal::STATUS_SIGNED);
        $deal->setCompleted_at(new \DateTime());
        $this->em->flush();

        $this->syncInvestmentByDeal($deal, 'signe');
    }

    /**
     * Called after Yousign confirms the signature is done (via sync or webhook).
     * The deal status is already set to SIGNED by YousignService — this method
     * only syncs the linked Investment.
     */
    public function markAsSignedFromYousign(Deal $deal): void
    {
        $this->syncInvestmentByDeal($deal, 'signe');
    }

    // ── Completion ────────────────────────────────────────────────────────────

    /**
     * Mark deal as fully completed (after contract download).
     * Updates Investment statut → 'termine'.
     */
    public function complete(Deal $deal): void
    {
        if ($deal->getStatus() === Deal::STATUS_SIGNED) {
            $deal->setStatus(Deal::STATUS_COMPLETED);
            $this->em->flush();

            $this->syncInvestmentByDeal($deal, 'termine');
        }
    }

    // ── Lookup helpers ────────────────────────────────────────────────────────

    public function findDealByNegotiation(Negotiation $negotiation): ?Deal
    {
        return $this->dealRepository->findOneBy([
            'negotiation_id' => $negotiation->getNegotiation_id(),
        ]);
    }

    public function findDealByInvestment(Investment $investment): ?Deal
    {
        return $this->dealRepository->findOneBy([
            'project_id' => $investment->getProject()?->getProject_id(),
            'buyer_id'   => $investment->getUser()?->getUserId(),
        ]);
    }

    // ── Guards ────────────────────────────────────────────────────────────────

    public function canPay(Deal $deal): bool
    {
        return $deal->getStatus() === Deal::STATUS_PENDING_PAYMENT;
    }

    public function canSign(Deal $deal): bool
    {
        return $deal->getStatus() === Deal::STATUS_PENDING_SIGNATURE;
    }

    public function canDownload(Deal $deal): bool
    {
        return in_array($deal->getStatus(), [Deal::STATUS_SIGNED, Deal::STATUS_COMPLETED], true)
            && $deal->getContract_pdf_path() !== null;
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    /**
     * Find the Investment linked to a Deal and update its statut.
     * Silent: never throws if no Investment is found (orphaned deal edge case).
     */
    private function syncInvestmentByDeal(Deal $deal, string $statut): void
    {
        $investment = $this->investmentRepo->findOneByProjectIdAndBuyerId(
            $deal->getProject_id(),
            $deal->getBuyer_id()
        );

        if ($investment !== null && $investment->getStatut() !== $statut) {
            $investment->setStatut($statut);
            $this->em->flush();
        }
    }

    private function assertStatus(Deal $deal, string $expected, string $message): void
    {
        if ($deal->getStatus() !== $expected) {
            throw new \LogicException($message);
        }
    }
}
