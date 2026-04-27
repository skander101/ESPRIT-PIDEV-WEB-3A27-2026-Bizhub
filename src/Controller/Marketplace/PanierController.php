<?php

namespace App\Controller\Marketplace;

use App\Entity\Marketplace\Panier;
use App\Entity\UsersAvis\User;
use App\Entity\Marketplace\ProduitService;
use App\Repository\Marketplace\PanierRepository;
use App\Repository\Marketplace\ProduitServiceRepository;
use App\Service\Marketplace\RecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/marketplace/panier', name: 'panier_')]
class PanierController extends AbstractController
{
    private function getUserId(): int
    {
        $user = $this->getUser();
        return $user ? (int) ($user instanceof User ? $user->getUserId() : null) : 0;
    }

    private function requireLogin(): ?Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        return null;
    }

    /** Calcule les totaux HT/TVA/TTC du panier */
    private function calculerTotaux(array $items, ProduitServiceRepository $produitRepo): array
    {
        $details = [];
        $totalHt = 0.0;

        foreach ($items as $item) {
            $produit  = $produitRepo->find($item->getIdProduit());
            $prixUnit = $produit ? (float) $produit->getPrix() : 0.0;
            $ht       = $prixUnit * $item->getQuantite();
            $totalHt += $ht;
            $details[] = [
                'item'      => $item,
                'produit'   => $produit,
                'prix_unit' => $prixUnit,
                'total_ht'  => $ht,
            ];
        }

        $tva = $totalHt * 0.19;
        return [
            'details'  => $details,
            'total_ht' => $totalHt,
            'tva'      => $tva,
            'ttc'      => $totalHt + $tva,
        ];
    }

    // ════════════════════════════════════════════════════════════════════
    //  AFFICHAGE
    // ════════════════════════════════════════════════════════════════════

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        PanierRepository      $panierRepo,
        ProduitServiceRepository $produitRepo,
        RecommendationService $recommandation,
    ): Response {
        if ($r = $this->requireLogin()) return $r;

        $userId = $this->getUserId();
        $items  = $panierRepo->findByClient($userId);
        $totaux = $this->calculerTotaux($items, $produitRepo);

        $suggestions = $recommandation->getCartSuggestions(
            $totaux['details'],
            $userId,
            4
        );

        return $this->render('front/Marketplace/panier/index.html.twig', array_merge(
            $totaux,
            ['suggestions' => $suggestions]
        ));
    }

    // ════════════════════════════════════════════════════════════════════
    //  AJOUTER
    // ════════════════════════════════════════════════════════════════════

    #[Route('/add/{id}', name: 'add', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function add(
        ProduitService $produit,
        Request $request,
        PanierRepository $panierRepo,
        EntityManagerInterface $em
    ): Response {
        if ($r = $this->requireLogin()) return $r;

        $userId = $this->getUserId();
        $qte    = max(1, (int) $request->request->get('quantite', 1));

        if (!$produit->isDisponible()) {
            $this->addFlash('warning', '«' . $produit->getNom() . '» n\'est plus disponible.');
            return $this->redirectToRoute('produit_index');
        }

        if ($qte > $produit->getQuantite()) {
            $this->addFlash('warning', 'Quantité demandée supérieure au stock (' . $produit->getQuantite() . ').');
            return $this->redirectToRoute('produit_show', ['id' => $produit->getIdProduit()]);
        }

        $existing = $panierRepo->findItem($userId, $produit->getIdProduit());
        if ($existing) {
            $newQte = min($existing->getQuantite() + $qte, $produit->getQuantite());
            $existing->setQuantite($newQte);
        } else {
            $em->persist(
                (new Panier())
                    ->setIdClient($userId)
                    ->setIdProduit($produit->getIdProduit())
                    ->setQuantite($qte)
            );
        }

        $em->flush();
        $this->addFlash('success', '🛒 «' . $produit->getNom() . '» ajouté au panier (x' . $qte . ').');

        $redirect = $request->request->get('redirect', 'panier');
        return $redirect === 'catalogue'
            ? $this->redirectToRoute('produit_index')
            : $this->redirectToRoute('panier_index');
    }

    // ════════════════════════════════════════════════════════════════════
    //  MODIFIER QUANTITÉ
    // ════════════════════════════════════════════════════════════════════

    #[Route('/update/{id}', name: 'update', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function update(
        Panier $item,
        Request $request,
        ProduitServiceRepository $produitRepo,
        EntityManagerInterface $em
    ): Response {
        if ($r = $this->requireLogin()) return $r;
        if ($item->getIdClient() !== $this->getUserId()) throw $this->createAccessDeniedException();

        $qte     = max(1, (int) $request->request->get('quantite', 1));
        $produit = $produitRepo->find($item->getIdProduit());

        if ($produit && $qte > $produit->getQuantite()) {
            $qte = $produit->getQuantite();
            $this->addFlash('warning', 'Quantité ajustée au stock (' . $qte . ').');
        }

        $item->setQuantite($qte);
        $em->flush();
        $this->addFlash('success', 'Quantité mise à jour.');
        return $this->redirectToRoute('panier_index');
    }

    // ════════════════════════════════════════════════════════════════════
    //  RETIRER UN ARTICLE
    // ════════════════════════════════════════════════════════════════════

    #[Route('/remove/{id}', name: 'remove', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function remove(Panier $item, Request $request, EntityManagerInterface $em): Response
    {
        if ($r = $this->requireLogin()) return $r;
        if ($item->getIdClient() !== $this->getUserId()) throw $this->createAccessDeniedException();

        if ($this->isCsrfTokenValid('remove_panier_' . $item->getIdPanier(), $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Article retiré du panier.');
        }
        return $this->redirectToRoute('panier_index');
    }

    // ════════════════════════════════════════════════════════════════════
    //  VIDER
    // ════════════════════════════════════════════════════════════════════

    #[Route('/vider', name: 'vider', methods: ['POST'])]
    public function vider(Request $request, PanierRepository $panierRepo): Response
    {
        if ($r = $this->requireLogin()) return $r;

        if ($this->isCsrfTokenValid('vider_panier', $request->request->get('_token'))) {
            $panierRepo->viderPanier($this->getUserId());
            $this->addFlash('success', '🗑️ Panier vidé.');
        }
        return $this->redirectToRoute('panier_index');
    }

    // ════════════════════════════════════════════════════════════════════
    //  SIDEBAR OFFCANVAS (partial HTML pour le drawer navbar)
    // ════════════════════════════════════════════════════════════════════

    #[Route('/sidebar', name: 'sidebar', methods: ['GET'])]
    public function sidebar(
        PanierRepository         $panierRepo,
        ProduitServiceRepository $produitRepo,
        RecommendationService    $recommandation,
    ): Response {
        if (!$this->getUser()) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }
        $userId = $this->getUserId();
        $items  = $panierRepo->findByClient($userId);
        $totaux = $this->calculerTotaux($items, $produitRepo);

        $suggestions = $recommandation->getCartSuggestions(
            $totaux['details'],
            $userId,
            3
        );

        return $this->render('front/Marketplace/panier/_sidebar.html.twig', array_merge(
            $totaux,
            ['suggestions' => $suggestions]
        ));
    }

    // ════════════════════════════════════════════════════════════════════
    //  BADGE AJAX
    // ════════════════════════════════════════════════════════════════════

    #[Route('/badge', name: 'badge', methods: ['GET'])]
    public function badge(PanierRepository $panierRepo): JsonResponse
    {
        if (!$this->getUser()) {
            return new JsonResponse(['count' => 0]);
        }
        return new JsonResponse(['count' => $panierRepo->countByClient($this->getUserId())]);
    }
}
