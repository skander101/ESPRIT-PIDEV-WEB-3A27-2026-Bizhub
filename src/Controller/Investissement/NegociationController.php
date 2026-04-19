<?php

namespace App\Controller\Investissement;

use App\Entity\Investissement\Investment;
use App\Entity\Investissement\Negotiation;
use App\Entity\Investissement\NegotiationMessage;
use App\Repository\InvestmentRepository;
use App\Repository\NegotiationMessageRepository;
use App\Repository\ProjectRepository;
use App\Repository\NegotiationRepository;
use App\Service\Investissement\AiNegotiationService;
use App\Service\Investissement\DealWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/front/negociation')]
class NegociationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface      $em,
        private InvestmentRepository        $investmentRepo,
        private NegotiationRepository       $negotiationRepo,
        private NegotiationMessageRepository $messageRepo,
        private DealWorkflowService         $workflow,
        private ProjectRepository           $projectRepo,
        private AiNegotiationService        $aiService,
    ) {}

    // ────────────────────────────────────────────────────────────────────────
    // LISTE DES NÉGOCIATIONS
    // ────────────────────────────────────────────────────────────────────────

    #[Route('', name: 'app_negociation_index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if ($user->getUserType() === 'startup') {
            $negociations = $this->negotiationRepo->findByStartup($user);
        } else {
            $negociations = $this->negotiationRepo->findByInvestor($user);
        }

        return $this->render('front/negociation/index.html.twig', [
            'negociations' => $negociations,
            'user_type'   => $user->getUserType(),
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // CRÉER OU VOIR LA NÉGOCIATION LIÉE À UN INVESTISSEMENT
    // ────────────────────────────────────────────────────────────────────────

    #[Route('/creer/{id}', name: 'app_negociation_creer', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function creer(Request $request, int $id): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        /** @var \App\Entity\Investissement\Investment|null $investment */
        $investment = $this->investmentRepo->find($id);
        if (!$investment) {
            $this->addFlash('error', 'Investissement introuvable.');
            return $this->redirectToRoute('app_front_investissement_index');
        }

        // Seul l'investisseur propriétaire peut initier
        if ($investment->getUser()?->getUserId() !== $user->getUserId()) {
            throw $this->createAccessDeniedException();
        }

        // Vérifier si une négociation existe déjà
        $existing = $this->negotiationRepo->findOneBy([
            'project'  => $investment->getProject(),
            'investor' => $investment->getUser(),
        ]);

        if ($existing) {
            return $this->redirectToRoute('app_negociation_show', ['id' => $existing->getNegotiation_id()]);
        }

        // Créer la négociation
        $project  = $investment->getProject();
        $startup  = $project?->getUser();

        if (!$startup) {
            $this->addFlash('error', 'Impossible de trouver la startup propriétaire du projet.');
            return $this->redirectToRoute('app_front_investissement_index');
        }

        $negociation = new Negotiation();
        $negociation->setProject($project);
        $negociation->setInvestor($user);
        $negociation->setStartup($startup);
        $negociation->setStatus(Negotiation::STATUS_OPEN);
        $negociation->setProposed_amount($investment->getAmount());
        $negociation->setCreated_at(new \DateTime());
        $negociation->setUpdated_at(new \DateTime());

        $this->em->persist($negociation);

        // Message d'ouverture automatique
        $msg = new NegotiationMessage();
        $msg->setNegotiation($negociation);
        $msg->setUser($user);
        $msg->setMessage('Bonjour, je souhaite investir ' . number_format((float)$investment->getAmount(), 0, ',', ' ') . ' TND dans votre projet.');
        $msg->setMessage_type('offer');
        $msg->setProposed_amount($investment->getAmount());
        $msg->setCreated_at(new \DateTime());

        $this->em->persist($msg);
        $this->em->flush();

        // Mettre à jour le statut de l'investissement
        $investment->setStatut('en_negociation');
        $this->em->flush();

        $this->addFlash('success', 'Négociation ouverte avec succès.');
        return $this->redirectToRoute('app_negociation_show', ['id' => $negociation->getNegotiation_id()]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // CRÉER UNE NÉGOCIATION DIRECTEMENT DEPUIS UN PROJET (sans investissement)
    // ────────────────────────────────────────────────────────────────────────

    #[Route('/projet/{id}', name: 'app_negociation_creer_par_projet', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function creerParProjet(int $id): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $project = $this->projectRepo->find($id);
        if (!$project) {
            $this->addFlash('error', 'Projet introuvable.');
            return $this->redirectToRoute('app_front_projet_index');
        }

        $startup = $project->getUser();
        if (!$startup) {
            $this->addFlash('error', 'Impossible de trouver la startup propriétaire du projet.');
            return $this->redirectToRoute('app_front_projet_show', ['id' => $id]);
        }

        // L'investisseur ne peut pas négocier son propre projet
        if ($startup->getUserId() === $user->getUserId()) {
            $this->addFlash('error', 'Vous ne pouvez pas négocier votre propre projet.');
            return $this->redirectToRoute('app_front_projet_show', ['id' => $id]);
        }

        // Vérifier si une négociation existe déjà pour ce projet+investisseur
        $existing = $this->negotiationRepo->findOneBy([
            'project'  => $project,
            'investor' => $user,
        ]);

        if ($existing) {
            return $this->redirectToRoute('app_negociation_show', ['id' => $existing->getNegotiation_id()]);
        }

        // Créer la négociation (montant proposé à 0 — sera précisé dans la discussion)
        $negociation = new Negotiation();
        $negociation->setProject($project);
        $negociation->setInvestor($user);
        $negociation->setStartup($startup);
        $negociation->setStatus(Negotiation::STATUS_OPEN);
        $negociation->setProposed_amount(0);
        $negociation->setCreated_at(new \DateTime());
        $negociation->setUpdated_at(new \DateTime());

        $this->em->persist($negociation);

        // Message d'ouverture automatique
        $msg = new NegotiationMessage();
        $msg->setNegotiation($negociation);
        $msg->setUser($user);
        $msg->setMessage('Bonjour, je suis intéressé par votre projet et souhaite discuter des conditions d\'investissement.');
        $msg->setMessage_type('message');
        $msg->setCreated_at(new \DateTime());

        $this->em->persist($msg);
        $this->em->flush();

        $this->addFlash('success', 'Négociation ouverte avec succès. Proposez un montant dans la discussion.');
        return $this->redirectToRoute('app_negociation_show', ['id' => $negociation->getNegotiation_id()]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // VOIR UNE NÉGOCIATION
    // ────────────────────────────────────────────────────────────────────────

    #[Route('/{id}', name: 'app_negociation_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(
        #[MapEntity(mapping: ['id' => 'negotiation_id'])]
        Negotiation $negociation
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $this->assertParticipant($negociation, $user->getUserId());

        $deal     = $this->workflow->findDealByNegotiation($negociation);
        // Utiliser le repository dédié avec LEFT JOIN sur le sender pour
        // éviter les proxies Doctrine non initialisés (bug côté startup)
        $messages = $this->messageRepo->findByNegotiation($negociation);

        $myId      = (int) $user->getUserId();
        $isStartup = $negociation->getStartup()  && (int)$negociation->getStartup()->getUserId()  === $myId;
        $isInvestor = $negociation->getInvestor() && (int)$negociation->getInvestor()->getUserId() === $myId;

        return $this->render('front/negociation/show.html.twig', [
            'negociation' => $negociation,
            'messages'    => $messages,
            'deal'        => $deal,
            'my_id'       => $myId,
            'is_startup'  => $isStartup,
            'is_investor' => $isInvestor,
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // ENVOYER UN MESSAGE
    // ────────────────────────────────────────────────────────────────────────

    #[Route('/{id}/message', name: 'app_negociation_message', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function message(
        Request $request,
        #[MapEntity(mapping: ['id' => 'negotiation_id'])]
        Negotiation $negociation
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $this->assertParticipant($negociation, $user->getUserId());

        if (!$this->isCsrfTokenValid('msg_' . $negociation->getNegotiation_id(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('app_negociation_show', ['id' => $negociation->getNegotiation_id()]);
        }

        if ($negociation->getStatus() !== Negotiation::STATUS_OPEN) {
            $this->addFlash('error', 'Cette négociation est clôturée.');
            return $this->redirectToRoute('app_negociation_show', ['id' => $negociation->getNegotiation_id()]);
        }

        $text    = trim((string) $request->request->get('message', ''));
        $amount  = $request->request->get('proposed_amount');

        if ($text === '') {
            $this->addFlash('error', 'Le message ne peut pas être vide.');
            return $this->redirectToRoute('app_negociation_show', ['id' => $negociation->getNegotiation_id()]);
        }

        $msg = new NegotiationMessage();
        $msg->setNegotiation($negociation);
        $msg->setUser($user);
        $msg->setMessage($text);
        $msg->setMessage_type($amount ? 'offer' : 'message');
        $msg->setCreated_at(new \DateTime());

        if ($amount && is_numeric($amount) && (float)$amount > 0) {
            $msg->setProposed_amount((float)$amount);
            $negociation->setProposed_amount((float)$amount);
        }

        $negociation->setUpdated_at(new \DateTime());
        $this->em->persist($msg);
        $this->em->flush();

        return $this->redirectToRoute('app_negociation_show', ['id' => $negociation->getNegotiation_id()]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // ACCEPTER LA NÉGOCIATION (startup seulement)
    // ────────────────────────────────────────────────────────────────────────

    #[Route('/{id}/accepter', name: 'app_negociation_accepter', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function accepter(
        Request $request,
        #[MapEntity(mapping: ['id' => 'negotiation_id'])]
        Negotiation $negociation
    ): Response {
        $user = $this->getUser();
        if (!$user || !$negociation->getStartup() || (int)$user->getUserId() !== (int)$negociation->getStartup()->getUserId()) {
            throw $this->createAccessDeniedException('Seule la startup peut valider la négociation.');
        }

        if (!$this->isCsrfTokenValid('accept_' . $negociation->getNegotiation_id(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('app_negociation_show', ['id' => $negociation->getNegotiation_id()]);
        }

        if ($negociation->getStatus() !== Negotiation::STATUS_OPEN) {
            $this->addFlash('error', 'Cette négociation ne peut plus être modifiée.');
            return $this->redirectToRoute('app_negociation_show', ['id' => $negociation->getNegotiation_id()]);
        }

        $finalAmount = $request->request->get('final_amount');
        if ($finalAmount && is_numeric($finalAmount) && (float)$finalAmount > 0) {
            $negociation->setFinal_amount((float)$finalAmount);
        } else {
            $negociation->setFinal_amount($negociation->getProposed_amount());
        }

        $negociation->setStatus(Negotiation::STATUS_ACCEPTED);
        $negociation->setUpdated_at(new \DateTime());
        $this->em->flush();

        // Créer le Deal et synchroniser le statut de l'investissement → 'accepte'
        $deal = $this->workflow->acceptDeal($negociation);

        // Message de confirmation
        $msg = new NegotiationMessage();
        $msg->setNegotiation($negociation);
        $msg->setUser($user);
        $msg->setMessage('✅ Accord validé ! Le montant final est de ' . number_format((float)$negociation->getFinal_amount(), 0, ',', ' ') . ' TND. Passez maintenant au paiement.');
        $msg->setMessage_type('system');
        $msg->setCreated_at(new \DateTime());
        $this->em->persist($msg);
        $this->em->flush();

        $this->addFlash('success', 'Accord validé. Le paiement est maintenant disponible.');
        return $this->redirectToRoute('app_deal_show', ['id' => $deal->getDeal_id()]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // REJETER LA NÉGOCIATION (startup seulement)
    // ────────────────────────────────────────────────────────────────────────

    #[Route('/{id}/rejeter', name: 'app_negociation_rejeter', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function rejeter(
        Request $request,
        #[MapEntity(mapping: ['id' => 'negotiation_id'])]
        Negotiation $negociation
    ): Response {
        $user = $this->getUser();
        if (!$user || !$negociation->getStartup() || (int)$user->getUserId() !== (int)$negociation->getStartup()->getUserId()) {
            throw $this->createAccessDeniedException('Seule la startup peut rejeter la négociation.');
        }

        if (!$this->isCsrfTokenValid('reject_' . $negociation->getNegotiation_id(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('app_negociation_show', ['id' => $negociation->getNegotiation_id()]);
        }

        $negociation->setStatus(Negotiation::STATUS_REJECTED);
        $negociation->setUpdated_at(new \DateTime());

        $msg = new NegotiationMessage();
        $msg->setNegotiation($negociation);
        $msg->setUser($user);
        $msg->setMessage('❌ La négociation a été refusée.');
        $msg->setMessage_type('system');
        $msg->setCreated_at(new \DateTime());
        $this->em->persist($msg);
        $this->em->flush();

        $this->addFlash('info', 'Négociation refusée.');
        return $this->redirectToRoute('app_negociation_show', ['id' => $negociation->getNegotiation_id()]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // ANALYSE IA (POST /front/negociation/{id}/analyser)
    // ────────────────────────────────────────────────────────────────────────

    #[Route('/{id}/analyser', name: 'app_negociation_analyser', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function analyser(
        Request $request,
        #[MapEntity(mapping: ['id' => 'negotiation_id'])]
        Negotiation $negociation
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Non authentifié.'], 401);
        }

        $this->assertParticipant($negociation, $user->getUserId());

        if (!$this->isCsrfTokenValid('ai_analyse_' . $negociation->getNegotiation_id(), $request->request->get('_token'))) {
            return $this->json(['error' => 'Token invalide.'], 403);
        }

        $messages = $this->messageRepo->findByNegotiation($negociation);

        // Detect role: is the current user the investor or the startup?
        $userType = ($negociation->getInvestor() && $negociation->getInvestor()->getUserId() === $user->getUserId())
            ? 'investor'
            : 'startup';

        // The service never throws — it always returns a result (OpenAI or local fallback)
        $result = $this->aiService->analyse($negociation, $messages, $userType);
        return $this->json($result);
    }

    // ────────────────────────────────────────────────────────────────────────
    // BROUILLON IA (POST /front/negociation/{id}/draft)
    // ────────────────────────────────────────────────────────────────────────

    #[Route('/{id}/draft', name: 'app_negociation_draft', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function draft(
        Request $request,
        #[MapEntity(mapping: ['id' => 'negotiation_id'])]
        Negotiation $negociation
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Non authentifié.'], 401);
        }

        $this->assertParticipant($negociation, $user->getUserId());

        if (!$this->isCsrfTokenValid('neg_draft_' . $negociation->getNegotiation_id(), $request->request->get('_token', ''))) {
            return $this->json(['error' => 'Token invalide. Rechargez la page.'], 403);
        }

        $style = trim((string) $request->request->get('style', 'professionnel'));
        if (!in_array($style, ['professionnel', 'diplomatique', 'direct', 'persuasif'], true)) {
            $style = 'professionnel';
        }

        $messages = $this->messageRepo->findByNegotiation($negociation);

        $draft = $this->aiService->generateDraft($negociation, $messages, $style);

        return $this->json(['draft' => $draft, 'style' => $style]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // INTERNAL
    // ────────────────────────────────────────────────────────────────────────

    private function assertParticipant(Negotiation $neg, int $userId): void
    {
        // Utiliser == (égalité lâche) pour éviter les problèmes de type avec les proxies Doctrine
        $isInvestor = $neg->getInvestor() && (int)$neg->getInvestor()->getUserId() === $userId;
        $isStartup  = $neg->getStartup()  && (int)$neg->getStartup()->getUserId()  === $userId;

        if (!$isInvestor && !$isStartup) {
            throw $this->createAccessDeniedException('Accès refusé à cette négociation.');
        }
    }
}
