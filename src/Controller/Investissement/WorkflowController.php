<?php

namespace App\Controller\Investissement;

use App\Entity\Investissement\Deal;
use App\Entity\Investissement\Investment;
use App\Entity\Investissement\Negotiation;
use App\Entity\UsersAvis\User;
use App\Repository\InvestmentRepository;
use App\Repository\NegotiationRepository;
use App\Service\Investissement\DealWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Read-only workflow overview for an investor investment.
 * Shows the complete pipeline: Intérêt → Négociation → Accord → Paiement → Signature → Finalisé
 */
#[Route('/front/workflow')]
class WorkflowController extends AbstractController
{
    public function __construct(
        private InvestmentRepository  $investmentRepo,
        private NegotiationRepository $negotiationRepo,
        private DealWorkflowService   $workflow,
        private EntityManagerInterface $em,
    ) {}

    #[Route('/investissement/{id}', name: 'app_workflow_investissement', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function investissement(
        #[MapEntity(mapping: ['id' => 'investment_id'])]
        Investment $investment
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $investorId = $investment->getUser()?->getUserId();
        $projectUser = $investment->getProject()?->getUser();
        $startupId  = $projectUser?->getUserId();

        // Only the investor or the startup owning the project may view this
        if ($user->getUserId() !== $investorId && $user->getUserId() !== $startupId) {
            throw $this->createAccessDeniedException('Accès refusé à ce workflow.');
        }

        // Find negotiation
        $negotiation = $this->negotiationRepo->findOneBy([
            'project'  => $investment->getProject(),
            'investor' => $investment->getUser(),
        ]);

        // Find deal
        $deal = $negotiation
            ? $this->workflow->findDealByNegotiation($negotiation)
            : $this->workflow->findDealByInvestment($investment);

        // Resolve Users for deal
        $buyer  = null;
        $seller = null;
        if ($deal) {
            $buyer  = $this->em->getRepository(User::class)->find($deal->getBuyer_id());
            $seller = $this->em->getRepository(User::class)->find($deal->getSeller_id());
        }

        // ── Compute workflow stage ────────────────────────────────────────────
        $stage = $this->computeStage($negotiation, $deal);

        // ── Current step number (1-6) ─────────────────────────────────────────
        $stepNum = match($stage) {
            'interet'      => 1,
            'negociation'  => 2,
            'accord'      => 3,
            'paiement'    => 3,
            'signature'   => 4,
            'signe'       => 5,
            'finalise'    => 6,
            default       => 1, // rejete, annule → back to 1 visually
        };

        return $this->render('front/workflow/investissement.html.twig', [
            'investment'  => $investment,
            'negotiation' => $negotiation,
            'deal'        => $deal,
            'buyer'       => $buyer,
            'seller'      => $seller,
            'stage'       => $stage,
            'step_num'    => $stepNum,
            'is_investor' => ($user->getUserId() === $investorId),
            'is_startup'  => ($user->getUserId() === $startupId),
            'can_pay'     => $deal && $this->workflow->canPay($deal),
            'can_sign'    => $deal && $this->workflow->canSign($deal),
            'can_download'=> $deal && $this->workflow->canDownload($deal),
        ]);
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    private function computeStage(?Negotiation $neg, ?Deal $deal): string
    {
        if ($neg === null) {
            return 'interet';
        }

        if ($neg->getStatus() === Negotiation::STATUS_REJECTED) {
            return 'rejete';
        }

        if ($deal === null) {
            return 'negociation'; // open OR accepted but deal not yet created
        }

        return match($deal->getStatus()) {
            Deal::STATUS_PENDING_PAYMENT  => 'accord',
            Deal::STATUS_PAID             => 'paiement',
            Deal::STATUS_PENDING_SIGNATURE => 'signature',
            Deal::STATUS_SIGNED           => 'signe',
            Deal::STATUS_COMPLETED        => 'finalise',
            Deal::STATUS_CANCELLED        => 'annule',
            default                       => 'accord',
        };
    }
}
