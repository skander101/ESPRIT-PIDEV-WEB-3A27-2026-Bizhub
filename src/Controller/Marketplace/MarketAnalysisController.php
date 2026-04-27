<?php

namespace App\Controller\Marketplace;

use App\Repository\Marketplace\CommandeRepository;
use App\Repository\Marketplace\ProduitServiceRepository;
use App\Service\Marketplace\GrokService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Analyse de marché générée par Grok/IA pour un produit ou depuis une commande.
 */
#[Route('/marketplace/analyse-marche', name: 'market_analysis_')]
class MarketAnalysisController extends AbstractController
{
    private function requireLogin(): ?Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        return null;
    }

    // ════════════════════════════════════════════════════════════════════
    //  ANALYSE D'UN PRODUIT
    // ════════════════════════════════════════════════════════════════════

    #[Route('/produit/{id}', name: 'produit', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function produit(
        int                      $id,
        ProduitServiceRepository $produitRepo,
        GrokService              $grok,
    ): Response {
        if ($r = $this->requireLogin()) return $r;

        $produit = $produitRepo->find($id);
        if (!$produit) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        $analysis = $grok->generateMarketAnalysis(
            $produit->getNom(),
            method_exists($produit, 'getCategorie') ? $produit->getCategorie() : null,
            (float) $produit->getPrix()
        );

        return $this->render('front/Marketplace/market_analysis/show.html.twig', [
            'context'  => 'produit',
            'produit'  => $produit,
            'commande' => null,
            'analysis' => $analysis,
            'back_url' => $this->generateUrl('produit_index'),
            'back_label' => 'Retour au marketplace',
        ]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  ANALYSE DEPUIS UNE COMMANDE (premier produit de la commande)
    // ════════════════════════════════════════════════════════════════════

    #[Route('/commande/{id}', name: 'commande', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function commande(
        int                      $id,
        CommandeRepository       $commandeRepo,
        ProduitServiceRepository $produitRepo,
        GrokService              $grok,
    ): Response {
        if ($r = $this->requireLogin()) return $r;

        $commande = $commandeRepo->find($id);
        if (!$commande) {
            throw $this->createNotFoundException('Commande introuvable.');
        }

        // Accès : startup (propriétaire) ou investisseur
        $user = $this->getUser();
        $userId = (int) $user->getUserId();

        if ($user->getUserType() === 'startup' && $commande->getIdClient() !== $userId) {
            throw $this->createAccessDeniedException();
        }

        // Récupérer le premier produit de la commande
        $ligne   = $commande->getLignes()->first();
        $produit = $ligne ? $produitRepo->find($ligne->getIdProduit()) : null;

        if (!$produit) {
            $this->addFlash('warning', 'Aucun produit associé à cette commande.');
            return $this->redirectToRoute('commande_show', ['id' => $id]);
        }

        $analysis = $grok->generateMarketAnalysis(
            $produit->getNom(),
            method_exists($produit, 'getCategorie') ? $produit->getCategorie() : null,
            (float) $produit->getPrix()
        );

        return $this->render('front/Marketplace/market_analysis/show.html.twig', [
            'context'    => 'commande',
            'produit'    => $produit,
            'commande'   => $commande,
            'analysis'   => $analysis,
            'back_url'   => $this->generateUrl('commande_show', ['id' => $id]),
            'back_label' => 'Retour à la commande',
        ]);
    }
}
