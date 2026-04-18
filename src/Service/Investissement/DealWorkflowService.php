<?php

namespace App\Service\Investissement;

use App\Entity\Investissement\Deal;
use App\Entity\Investissement\Investment;
use App\Entity\Investissement\Negotiation;
use App\Repository\Investissement\DealRepository;
use App\Repository\InvestmentRepository;
use Doctrine\ORM\EntityManagerInterface;

class DealWorkflowService
{
    public function __construct(
        private EntityManagerInterface $em,
        private DealRepository         $dealRepository,
        private InvestmentRepository   $investmentRepository,
    ) {}

    // ── Création ──────────────────────────────────────────────────────────────

    /**
     * Accepte une négociation et crée le Deal correspondant.
     */
    public function acceptDeal(Negotiation $negotiation): Deal
    {
        $deal = new Deal();
        $deal->setNegotiation_id($negotiation->getNegotiation_id());
        $deal->setProject_id($negotiation->getProject()?->getProject_id() ?? 0);
        $deal->setBuyer_id($negotiation->getInvestor()?->getUserId() ?? 0);
        $deal->setSeller_id($negotiation->getStartup()?->getUserId() ?? 0);
        $deal->setAmount($negotiation->getFinal_amount() ?? $negotiation->getProposed_amount() ?? 0);
        $deal->setStatus(Deal::STATUS_PENDING_PAYMENT);
        $deal->setCreated_at(new \DateTime());

        $this->em->persist($deal);

        // Mettre à jour le statut de l'investissement lié
        $investment = $this->investmentRepository->findOneByProjectIdAndBuyerId(
            $deal->getProject_id(),
            $deal->getBuyer_id()
        );
        if ($investment) {
            $investment->setStatut('accepte');
        }

        $this->em->flush();

        return $deal;
    }

    /**
     * Crée un deal à partir d'un investissement accepté (alternative sans négociation).
     */
    public function createFromInvestment(Investment $investment, int $sellerId): Deal
    {
        $deal = new Deal();
        $deal->setProject_id($investment->getProject()?->getProject_id() ?? 0);
        $deal->setBuyer_id($investment->getUser()?->getUserId() ?? 0);
        $deal->setSeller_id($sellerId);
        $deal->setAmount($investment->getAmount());
        $deal->setStatus(Deal::STATUS_PENDING_PAYMENT);
        $deal->setCreated_at(new \DateTime());

        $this->em->persist($deal);
        $this->em->flush();

        return $deal;
    }

    // ── Transitions ───────────────────────────────────────────────────────────

    public function markAsPaid(Deal $deal, string $stripeSessionId, string $paymentIntentId = ''): void
    {
        $deal->setStatus(Deal::STATUS_PAID);
        $deal->setStripe_checkout_session_id($stripeSessionId);
        if ($paymentIntentId) {
            $deal->setStripe_payment_intent_id($paymentIntentId);
        }
        $deal->setStripe_payment_status('succeeded');
        $this->em->flush();
    }

    public function generateContract(Deal $deal, string $contractPath): void
    {
        $deal->setContract_pdf_path($contractPath);
        $deal->setStatus(Deal::STATUS_PENDING_SIGNATURE);

        // Générer un token de signature (48h de validité)
        $token = bin2hex(random_bytes(32));
        $deal->setSignature_token($token);
        $deal->setSignature_token_expires_at((new \DateTime())->modify('+48 hours'));

        $this->em->flush();
    }

    public function signContractByToken(Deal $deal, string $token): void
    {
        if ($deal->getSignature_token() !== $token) {
            throw new \LogicException('Token de signature invalide.');
        }
        if ($deal->getSignature_token_expires_at() < new \DateTime()) {
            throw new \LogicException('Le lien de signature a expiré.');
        }
        if ($deal->getStatus() !== Deal::STATUS_PENDING_SIGNATURE) {
            throw new \LogicException('Ce contrat n\'est plus en attente de signature.');
        }

        $deal->setStatus(Deal::STATUS_SIGNED);
        $deal->setSignature_token(null);
        $deal->setSignature_token_expires_at(null);

        $this->syncInvestmentStatus($deal, 'signe');
        $this->em->flush();
    }

    public function markAsSignedFromYousign(Deal $deal): void
    {
        if ($deal->getStatus() === Deal::STATUS_SIGNED) {
            return;
        }
        $deal->setStatus(Deal::STATUS_SIGNED);
        $this->syncInvestmentStatus($deal, 'signe');
        $this->em->flush();
    }

    public function complete(Deal $deal): void
    {
        if ($deal->getStatus() === Deal::STATUS_COMPLETED) {
            return;
        }
        $deal->setStatus(Deal::STATUS_COMPLETED);
        $deal->setCompleted_at(new \DateTime());
        $this->syncInvestmentStatus($deal, 'termine');
        $this->em->flush();
    }

    public function cancel(Deal $deal): void
    {
        $deal->setStatus(Deal::STATUS_CANCELLED);
        $this->em->flush();
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
        return in_array($deal->getStatus(), [
            Deal::STATUS_SIGNED,
            Deal::STATUS_COMPLETED,
        ], true) && $deal->getContract_pdf_path() !== null;
    }

    // ── Finders ───────────────────────────────────────────────────────────────

    public function findDealByNegotiation(Negotiation $negotiation): ?Deal
    {
        return $this->dealRepository->findOneBy([
            'negotiation_id' => $negotiation->getNegotiation_id(),
        ]);
    }

    public function findDealByInvestment(Investment $investment): ?Deal
    {
        $projectId = $investment->getProject()?->getProject_id();
        $buyerId   = $investment->getUser()?->getUserId();

        if (!$projectId || !$buyerId) {
            return null;
        }

        return $this->dealRepository->findOneBy([
            'project_id' => $projectId,
            'buyer_id'   => $buyerId,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function syncInvestmentStatus(Deal $deal, string $statut): void
    {
        $investment = $this->investmentRepository->findOneByProjectIdAndBuyerId(
            $deal->getProject_id(),
            $deal->getBuyer_id()
        );
        if ($investment) {
            $investment->setStatut($statut);
        }
    }
}
