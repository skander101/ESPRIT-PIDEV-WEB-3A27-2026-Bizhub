<?php

namespace App\Controller\Investissement;

use App\Entity\Investissement\Deal;
use App\Entity\Investissement\Investment;
use App\Entity\Investissement\Negotiation;
use App\Entity\Investissement\NegotiationMessage;
use App\Entity\Investissement\Project;
use App\Repository\InvestmentRepository;
use App\Repository\NegotiationMessageRepository;
use App\Repository\NegotiationRepository;
use App\Repository\ProjectRepository;
use App\Service\Investissement\AiNegotiationService;
use App\Service\Investissement\DealWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gestion complète des négociations d'investissement.
 *
 * Flux :
 *   investisseur initie → messages ↔ contre-offres → startup accepte/rejette
 *                                                           ↓ (accepte)
 *                                                     DealWorkflowService::acceptDeal()
 *                                                     → Deal créé (pending_payment)
 */
#[Route('/front/negociation')]
class NegociationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface    $em,
        private NegotiationRepository     $negRepo,
        private NegotiationMessageRepository $msgRepo,
        private ProjectRepository         $projectRepo,
        private InvestmentRepository      $investmentRepo,
        private DealWorkflowService       $dealWorkflow,
        private AiNegotiationService      $aiService,
    ) {}

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function requireLogin(): ?Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        return null;
    }

    private function userId(): int
    {
        return (int) $this->getUser()?->getUserId();
    }

    private function canAccessNegotiation(Negotiation $neg): bool
    {
        $uid = $this->userId();
        return $uid === (int) $neg->getInvestor()?->getUserId()
            || $uid === (int) $neg->getStartup()?->getUserId();
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  1. LISTE DES NÉGOCIATIONS (pour un utilisateur)
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('', name: 'app_negociation_index', methods: ['GET'])]
    public function index(): Response
    {
        if ($r = $this->requireLogin()) return $r;

        $user = $this->getUser();
        $type = $user->getUserType();

        $negociations = match ($type) {
            'investisseur' => $this->negRepo->findByInvestor($user),
            'startup'      => $this->negRepo->findByStartup($user),
            default        => [],
        };

        return $this->render('front/negociation/index.html.twig', [
            'negociations' => $negociations,
            'user_type'    => $type,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  2. INITIER UNE NÉGOCIATION (investisseur → startup)
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('/initier/{projectId}', name: 'app_negociation_initier', methods: ['POST'], requirements: ['projectId' => '\d+'])]
    public function initier(int $projectId, Request $request): Response
    {
        if ($r = $this->requireLogin()) return $r;

        $user = $this->getUser();

        if ($user->getUserType() !== 'investisseur') {
            $this->addFlash('error', 'Seuls les investisseurs peuvent initier une négociation.');
            return $this->redirectToRoute('app_front_projet_index');
        }

        if (!$this->isCsrfTokenValid('initier_neg_' . $projectId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_front_projet_show', ['id' => $projectId]);
        }

        $projet = $this->projectRepo->find($projectId);
        if (!$projet || !in_array($projet->getStatus(), [Project::STATUS_PUBLIE, Project::STATUS_EN_COURS], true)) {
            $this->addFlash('error', 'Projet introuvable ou fermé aux négociations.');
            return $this->redirectToRoute('app_front_projet_index');
        }

        // Vérifie qu'une négociation ouverte n'existe pas déjà pour ce duo
        $existing = $this->negRepo->findOneBy([
            'project'  => $projet,
            'investor' => $user,
            'status'   => Negotiation::STATUS_OPEN,
        ]);
        if ($existing) {
            $this->addFlash('info', 'Vous avez déjà une négociation ouverte pour ce projet.');
            return $this->redirectToRoute('app_negociation_show', ['id' => $existing->getNegotiation_id()]);
        }

        $montant = (float) $request->request->get('montant', 0);
        if ($montant <= 0) {
            $this->addFlash('error', 'Le montant proposé doit être positif.');
            return $this->redirectToRoute('app_front_projet_show', ['id' => $projectId]);
        }

        $now = new \DateTime();
        $neg = (new Negotiation())
            ->setProject($projet)
            ->setInvestor($user)
            ->setStartup($projet->getUser())
            ->setStatus(Negotiation::STATUS_OPEN)
            ->setProposed_amount($montant)
            ->setCreated_at($now)
            ->setUpdated_at($now);

        $this->em->persist($neg);

        // Premier message automatique
        $intro = $request->request->get('message', '');
        if ($intro !== '') {
            $msg = (new NegotiationMessage())
                ->setNegotiation($neg)
                ->setUser($user)
                ->setMessage($intro)
                ->setMessage_type('initiation')
                ->setProposed_amount($montant)
                ->setCreated_at($now);
            $this->em->persist($msg);
        }

        $this->em->flush();

        $this->addFlash('success', 'Négociation initiée avec succès !');
        return $this->redirectToRoute('app_negociation_show', ['id' => $neg->getNegotiation_id()]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  3. VUE DÉTAILLÉE D'UNE NÉGOCIATION (chat + actions)
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('/{id}', name: 'app_negociation_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(
        #[MapEntity(mapping: ['id' => 'negotiation_id'])]
        Negotiation $neg
    ): Response {
        if ($r = $this->requireLogin()) return $r;
        if (!$this->canAccessNegotiation($neg)) {
            throw $this->createAccessDeniedException();
        }

        $messages = $this->msgRepo->findByNegotiation($neg);
        $user     = $this->getUser();
        $isInvestor = $this->userId() === (int) $neg->getInvestor()?->getUserId();

        // Deal associé éventuel
        $deal = $this->dealWorkflow->findDealByNegotiation($neg);

        return $this->render('front/negociation/show.html.twig', [
            'neg'         => $neg,
            'messages'    => $messages,
            'deal'        => $deal,
            'is_investor' => $isInvestor,
            'is_startup'  => !$isInvestor,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  4. ENVOYER UN MESSAGE
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('/{id}/message', name: 'app_negociation_message', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function sendMessage(
        #[MapEntity(mapping: ['id' => 'negotiation_id'])]
        Negotiation $neg,
        Request     $request
    ): Response {
        if ($r = $this->requireLogin()) return $r;
        if (!$this->canAccessNegotiation($neg)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('neg_msg_' . $neg->getNegotiation_id(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_negociation_show', ['id' => $neg->getNegotiation_id()]);
        }

        if ($neg->getStatus() !== Negotiation::STATUS_OPEN) {
            $this->addFlash('warning', 'Cette négociation n\'est plus ouverte.');
            return $this->redirectToRoute('app_negociation_show', ['id' => $neg->getNegotiation_id()]);
        }

        $texte = trim($request->request->get('message', ''));
        if ($texte === '') {
            $this->addFlash('error', 'Le message ne peut pas être vide.');
            return $this->redirectToRoute('app_negociation_show', ['id' => $neg->getNegotiation_id()]);
        }

        $now = new \DateTime();
        $msg = (new NegotiationMessage())
            ->setNegotiation($neg)
            ->setUser($this->getUser())
            ->setMessage($texte)
            ->setMessage_type('message')
            ->setCreated_at($now);

        $neg->setUpdated_at($now);

        $this->em->persist($msg);
        $this->em->flush();

        return $this->redirectToRoute('app_negociation_show', ['id' => $neg->getNegotiation_id()]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  5. CONTRE-OFFRE (nouveau montant proposé)
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('/{id}/contre-offre', name: 'app_negociation_contre_offre', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function contreOffre(
        #[MapEntity(mapping: ['id' => 'negotiation_id'])]
        Negotiation $neg,
        Request     $request
    ): Response {
        if ($r = $this->requireLogin()) return $r;
        if (!$this->canAccessNegotiation($neg)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('neg_contre_' . $neg->getNegotiation_id(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_negociation_show', ['id' => $neg->getNegotiation_id()]);
        }

        if ($neg->getStatus() !== Negotiation::STATUS_OPEN) {
            $this->addFlash('warning', 'Négociation fermée — contre-offre impossible.');
            return $this->redirectToRoute('app_negociation_show', ['id' => $neg->getNegotiation_id()]);
        }

        $montant = (float) $request->request->get('montant', 0);
        if ($montant <= 0) {
            $this->addFlash('error', 'Le montant de la contre-offre doit être positif.');
            return $this->redirectToRoute('app_negociation_show', ['id' => $neg->getNegotiation_id()]);
        }

        $now = new \DateTime();
        $neg->setProposed_amount($montant)->setUpdated_at($now);

        $user    = $this->getUser();
        $role    = $this->userId() === (int) $neg->getInvestor()?->getUserId() ? 'investisseur' : 'startup';
        $texte   = sprintf('[Contre-offre %s] Nouveau montant proposé : %s TND', $role, number_format($montant, 2, '.', ' '));

        $msg = (new NegotiationMessage())
            ->setNegotiation($neg)
            ->setUser($user)
            ->setMessage($texte)
            ->setMessage_type('contre_offre')
            ->setProposed_amount($montant)
            ->setCreated_at($now);

        $this->em->persist($msg);
        $this->em->flush();

        $this->addFlash('success', 'Contre-offre envoyée : ' . number_format($montant, 2, '.', ' ') . ' TND');
        return $this->redirectToRoute('app_negociation_show', ['id' => $neg->getNegotiation_id()]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  6. ACCEPTER LA NÉGOCIATION (startup) → crée un Deal
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('/{id}/accepter', name: 'app_negociation_accepter', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function accepter(
        #[MapEntity(mapping: ['id' => 'negotiation_id'])]
        Negotiation $neg,
        Request     $request
    ): Response {
        if ($r = $this->requireLogin()) return $r;

        $user = $this->getUser();
        if ($this->userId() !== (int) $neg->getStartup()?->getUserId()) {
            throw $this->createAccessDeniedException('Seule la startup peut accepter cette négociation.');
        }

        if (!$this->isCsrfTokenValid('neg_accepter_' . $neg->getNegotiation_id(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_negociation_show', ['id' => $neg->getNegotiation_id()]);
        }

        if ($neg->getStatus() !== Negotiation::STATUS_OPEN) {
            $this->addFlash('warning', 'Négociation déjà traitée.');
            return $this->redirectToRoute('app_negociation_show', ['id' => $neg->getNegotiation_id()]);
        }

        // Fixe le montant final au dernier montant proposé
        $neg->setStatus(Negotiation::STATUS_ACCEPTED)
            ->setFinal_amount($neg->getProposed_amount())
            ->setUpdated_at(new \DateTime());

        $this->em->flush();

        // Crée le Deal via le service existant
        $deal = $this->dealWorkflow->acceptDeal($neg);

        $this->addFlash('success', 'Négociation acceptée ! Un deal a été créé. Passez au paiement.');
        return $this->redirectToRoute('app_deal_show', ['id' => $deal->getDeal_id()]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  7. REJETER LA NÉGOCIATION (investisseur ou startup)
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('/{id}/rejeter', name: 'app_negociation_rejeter', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function rejeter(
        #[MapEntity(mapping: ['id' => 'negotiation_id'])]
        Negotiation $neg,
        Request     $request
    ): Response {
        if ($r = $this->requireLogin()) return $r;
        if (!$this->canAccessNegotiation($neg)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('neg_rejeter_' . $neg->getNegotiation_id(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_negociation_show', ['id' => $neg->getNegotiation_id()]);
        }

        if ($neg->getStatus() !== Negotiation::STATUS_OPEN) {
            $this->addFlash('warning', 'Négociation déjà traitée.');
            return $this->redirectToRoute('app_negociation_show', ['id' => $neg->getNegotiation_id()]);
        }

        $neg->setStatus(Negotiation::STATUS_REJECTED)
            ->setUpdated_at(new \DateTime());
        $this->em->flush();

        $this->addFlash('info', 'Négociation rejetée.');
        return $this->redirectToRoute('app_negociation_index');
    }

    // =========================================================================
    //  ENDPOINTS JSON (pour AJAX / polling frontend)
    // =========================================================================

    /**
     * GET /api/negociation/{id}/messages
     * Retourne les messages d'une négociation au format JSON.
     * Utilisé pour le polling AJAX (actualisation du chat sans rechargement).
     */
    #[Route('/api/{id}/messages', name: 'api_negociation_messages', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function messagesJson(
        #[MapEntity(mapping: ['id' => 'negotiation_id'])]
        Negotiation $neg
    ): JsonResponse {
        if (!$this->getUser()) {
            return $this->json(['error' => 'Non authentifié.'], 401);
        }
        if (!$this->canAccessNegotiation($neg)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $messages = $this->msgRepo->findByNegotiation($neg);
        $data     = array_map(fn(NegotiationMessage $m) => [
            'id'              => $m->getMessage_id(),
            'sender_id'       => $m->getUser()?->getUserId(),
            'sender_name'     => $m->getUser()?->getFullName() ?? 'Inconnu',
            'message'         => $m->getMessage(),
            'message_type'    => $m->getMessage_type(),
            'proposed_amount' => $m->getProposed_amount(),
            'sentiment'       => $m->getSentiment(),
            'created_at'      => $m->getCreated_at()?->format('d/m/Y H:i'),
        ], $messages);

        return $this->json([
            'negotiation_id' => $neg->getNegotiation_id(),
            'status'         => $neg->getStatus(),
            'proposed_amount'=> $neg->getProposed_amount(),
            'messages'       => $data,
            'count'          => count($data),
        ]);
    }

    /**
     * POST /api/negociation/{id}/analyser
     * Analyse IA de la négociation (AiNegotiationService).
     * Retourne sentiment, points clés, risques, recommandation.
     */
    #[Route('/api/{id}/analyser', name: 'api_negociation_analyser', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function analyserAi(
        #[MapEntity(mapping: ['id' => 'negotiation_id'])]
        Negotiation $neg,
        Request     $request
    ): JsonResponse {
        if (!$this->getUser()) {
            return $this->json(['error' => 'Non authentifié.'], 401);
        }
        if (!$this->canAccessNegotiation($neg)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }
        if (!$this->isCsrfTokenValid('neg_ai_' . $neg->getNegotiation_id(), $request->request->get('_token'))) {
            return $this->json(['error' => 'Token de sécurité invalide.'], 403);
        }

        $messages  = $this->msgRepo->findByNegotiation($neg);
        $userType  = $this->userId() === (int) $neg->getInvestor()?->getUserId()
            ? 'investor'
            : 'startup';

        try {
            $result = $this->aiService->analyse($neg, $messages, $userType);
        } catch (\Throwable) {
            return $this->json(['error' => 'Analyse IA indisponible.'], 503);
        }

        return $this->json($result);
    }

    /**
     * POST /api/negociation/{id}/draft
     * Génère un brouillon de message IA (AiNegotiationService::generateDraft).
     */
    #[Route('/api/{id}/draft', name: 'api_negociation_draft', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function draftAi(
        #[MapEntity(mapping: ['id' => 'negotiation_id'])]
        Negotiation $neg,
        Request     $request
    ): JsonResponse {
        if (!$this->getUser()) {
            return $this->json(['error' => 'Non authentifié.'], 401);
        }
        if (!$this->canAccessNegotiation($neg)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }
        if (!$this->isCsrfTokenValid('neg_draft_' . $neg->getNegotiation_id(), $request->request->get('_token'))) {
            return $this->json(['error' => 'Token de sécurité invalide.'], 403);
        }

        $messages = $this->msgRepo->findByNegotiation($neg);
        $userType = $this->userId() === (int) $neg->getInvestor()?->getUserId()
            ? 'investor'
            : 'startup';

        try {
            $result = $this->aiService->generateDraft($neg, $messages, $userType);
        } catch (\Throwable) {
            return $this->json(['error' => 'Service IA indisponible.'], 503);
        }

        return $this->json($result);
    }
}
