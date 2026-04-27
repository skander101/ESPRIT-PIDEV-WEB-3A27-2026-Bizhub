<?php

namespace App\Controller\Marketplace;

use App\Entity\Marketplace\ProduitService;
use App\Form\Marketplace\ProduitType;
use App\Repository\Marketplace\ProduitServiceRepository;
use App\Service\Marketplace\RecommendationService;
use App\Service\Marketplace\StatisticsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/marketplace/produits', name: 'produit_')]
class ProduitController extends AbstractController
{
    // ──────────────────────────────────────────────────────────────────────────
    // Helper pour vérifier si l'utilisateur est investisseur
    // ──────────────────────────────────────────────────────────────────────────
    private function requireInvestisseur(): ?Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        if ($user->getUserType() !== 'investisseur') {
            $this->addFlash('error', '❌ Seuls les investisseurs peuvent créer et gérer des produits.');
            return $this->redirectToRoute('produit_index');
        }
        return null;
    }

    // ── Helper ───────────────────────────────────────────────────────────
    private function getUserId(): int
    {
        $user = $this->getUser();
        return $user ? (int) $user->getUserId() : 0;
    }

    // ════════════════════════════════════════════════════════════════════
    //  CATALOGUE PUBLIC
    // ════════════════════════════════════════════════════════════════════

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(ProduitServiceRepository $repo, Request $request, StatisticsService $statisticsService): Response
    {
        // Investors cannot browse the public catalog - redirect to their products
        $user = $this->getUser();
        if ($user && $user->getUserType() === 'investisseur') {
            return $this->redirectToRoute('produit_mes');
        }

        $q         = trim($request->query->get('q', ''));
        $categorie = trim($request->query->get('categorie', ''));

        $produits = match (true) {
            $q !== ''         => $repo->search($q),
            $categorie !== '' => $repo->findByCategorie($categorie),
            default           => $repo->findDisponibles(),
        };

        return $this->render('front/Marketplace/produits/index.html.twig', [
            'produits'     => $produits,
            'categories'   => $repo->findAllCategories(),
            'q'            => $q,
            'cat_active'   => $categorie,
            'top_produits' => $statisticsService->getTopProductsByFrequency(5),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(ProduitService $produit, RecommendationService $recommandation): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        // Investors cannot view product details (only startups can)
        if ($this->getUser()->getUserType() === 'investisseur') {
            return $this->redirectToRoute('produit_mes');
        }
        return $this->render('front/Marketplace/produits/show.html.twig', [
            'produit'   => $produit,
            'similaires' => $recommandation->getSimilarProducts($produit, 4),
        ]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  MES PRODUITS — CRUD investisseur
    // ════════════════════════════════════════════════════════════════════

    #[Route('/mes-produits', name: 'mes', methods: ['GET'])]
    public function mesProduits(ProduitServiceRepository $repo): Response
    {
        $response = $this->requireInvestisseur();
        if ($response) {
            return $response;
        }
        $produits       = $repo->findByOwner($this->getUserId());
        $stockAlertSeuil = 5;
        $stockAlertes   = array_filter($produits, fn($p) => $p->getQuantite() <= $stockAlertSeuil && $p->isDisponible());

        return $this->render('front/Marketplace/produits/mes_produits.html.twig', [
            'produits'         => $produits,
            'stock_alerte_ids' => array_map(fn($p) => $p->getIdProduit(), $stockAlertes),
            'stock_seuil'      => $stockAlertSeuil,
        ]);
    }

    #[Route('/mes-produits/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $response = $this->requireInvestisseur();
        if ($response) {
            return $response;
        }

        $userId  = $this->getUserId();
        $produit = (new ProduitService())
            ->setOwnerUserId($userId)
            ->setIdProfile($userId);

        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // VichUploaderBundle détecte imageFile sur l'entité et gère
            // automatiquement le déplacement + le nommage du fichier.
            // Plus besoin de code manuel ici.
            $em->persist($produit);
            $em->flush();
            $this->addFlash('success', '✅ Produit «' . $produit->getNom() . '» publié avec succès.');
            return $this->redirectToRoute('produit_mes');
        }

        return $this->render('front/Marketplace/produits/form.html.twig', [
            'form'  => $form->createView(),
            'titre' => 'Publier un produit / service',
            'mode'  => 'create',
        ]);
    }

    #[Route('/mes-produits/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(ProduitService $produit, Request $request, EntityManagerInterface $em): Response
    {
        $response = $this->requireInvestisseur();
        if ($response) {
            return $response;
        }
        if ($produit->getOwnerUserId() !== $this->getUserId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez modifier que vos propres produits.');
        }

        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vich détecte si une nouvelle image a été choisie :
            //   - si oui  → upload + suppression de l'ancienne (delete_on_update: true)
            //   - si non  → imageName reste inchangé
            // Aucun code manuel nécessaire.
            $em->flush();
            $this->addFlash('success', '✏️ Produit «' . $produit->getNom() . '» modifié.');
            return $this->redirectToRoute('produit_mes');
        }

        return $this->render('front/Marketplace/produits/form.html.twig', [
            'form'    => $form->createView(),
            'titre'   => 'Modifier ' . $produit->getNom(),
            'produit' => $produit,
            'mode'    => 'edit',
        ]);
    }

    #[Route('/mes-produits/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(ProduitService $produit, Request $request, EntityManagerInterface $em): Response
    {
        $response = $this->requireInvestisseur();
        if ($response) {
            return $response;
        }
        if ($produit->getOwnerUserId() !== $this->getUserId()) {
            throw $this->createAccessDeniedException();
        }
        if ($this->isCsrfTokenValid('delete_produit_' . $produit->getIdProduit(), $request->request->get('_token'))) {
            $nom = $produit->getNom();
            $em->remove($produit);
            $em->flush();
            $this->addFlash('success', '🗑️ Produit «' . $nom . '» supprimé.');
        }
        return $this->redirectToRoute('produit_mes');
    }

    #[Route('/mes-produits/{id}/toggle', name: 'toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggle(ProduitService $produit, Request $request, EntityManagerInterface $em): Response
    {
        $response = $this->requireInvestisseur();
        if ($response) {
            return $response;
        }
        if ($produit->getOwnerUserId() !== $this->getUserId()) {
            throw $this->createAccessDeniedException();
        }
        $produit->setDisponible(!$produit->isDisponible());
        $em->flush();
        $etat = $produit->isDisponible() ? 'disponible' : 'indisponible';
        $this->addFlash('success', '«' . $produit->getNom() . '» est maintenant ' . $etat . '.');
        return $this->redirectToRoute('produit_mes');
    }
}
