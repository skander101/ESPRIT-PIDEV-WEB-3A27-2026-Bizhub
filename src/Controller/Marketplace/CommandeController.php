<?php

namespace App\Controller\Marketplace;

use App\Entity\Marketplace\Commande;
use App\Entity\Marketplace\CommandeLigne;
use App\Repository\Marketplace\CommandeRepository;
use App\Repository\Marketplace\PanierRepository;
use App\Repository\Marketplace\ProduitServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/marketplace/commandes', name: 'commande_')]
class CommandeController extends AbstractController
{
    private function getUserId(): int
    {
        $user = $this->getUser();
        return $user ? (int) $user->getUserId() : 0;
    }

    private function requireLogin(): ?Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        return null;
    }

    private function requireStartup(): ?Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        if ($user->getUserType() !== 'startup') {
            $this->addFlash('error', '❌ Seules les startups peuvent passer des commandes.');
            return $this->redirectToRoute('produit_index');
        }
        return null;
    }

    private function requireInvestisseur(): ?Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        if ($user->getUserType() !== 'investisseur') {
            $this->addFlash('error', '❌ Seuls les investisseurs peuvent gérer les commandes reçues.');
            return $this->redirectToRoute('produit_index');
        }
        return null;
    }

    // ════════════════════════════════════════════════════════════════════
    //  MES COMMANDES — client
    // ════════════════════════════════════════════════════════════════════

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(CommandeRepository $repo, Request $request): Response
    {
        if ($r = $this->requireStartup()) return $r;

        $userId    = $this->getUserId();
        $statut    = $request->query->get('statut');
        $toutes    = $repo->findByClient($userId);
        $commandes = $statut
            ? array_values(array_filter($toutes, fn($c) => $c->getStatut() === $statut))
            : $toutes;

        $nbAttente   = count(array_filter($toutes, fn($c) => $c->getStatut() === Commande::STATUT_ATTENTE));
        $nbConfirmee = count(array_filter($toutes, fn($c) => $c->getStatut() === Commande::STATUT_CONFIRMEE));
        $nbAnnulee   = count(array_filter($toutes, fn($c) => $c->getStatut() === Commande::STATUT_ANNULEE));
        $nbLivree    = count(array_filter($toutes, fn($c) => $c->getStatut() === Commande::STATUT_LIVREE));

        return $this->render('front/marketplace/commandes/index.html.twig', [
            'commandes'     => $commandes,
            'statut_filtre' => $statut,
            'stats'         => [
                'total'      => count($toutes),
                'en_attente' => $nbAttente,
                'confirmees' => $nbConfirmee,
                'annulees'   => $nbAnnulee,
                'livrees'    => $nbLivree,
                'payees'     => count(array_filter($toutes, fn($c) => $c->isEstPayee())),
            ],
            'chart_data'    => [
                $nbAttente,
                $nbConfirmee,
                $nbAnnulee,
                $nbLivree,
            ],
            'statuts_dispo' => [
                Commande::STATUT_ATTENTE,
                Commande::STATUT_CONFIRMEE,
                Commande::STATUT_ANNULEE,
                Commande::STATUT_LIVREE,
            ],
        ]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  PASSER UNE COMMANDE (depuis panier)
    // ════════════════════════════════════════════════════════════════════

    #[Route('/passer', name: 'passer', methods: ['POST'])]
    public function passer(
        Request $request,
        PanierRepository $panierRepo,
        ProduitServiceRepository $produitRepo,
        EntityManagerInterface $em
    ): Response {
        if ($r = $this->requireStartup()) return $r;

        if (!$this->isCsrfTokenValid('passer_commande', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token de sécurité invalide.');
            return $this->redirectToRoute('panier_index');
        }

        $userId = $this->getUserId();
        $items  = $panierRepo->findByClient($userId);

        if (empty($items)) {
            $this->addFlash('warning', '⚠️ Votre panier est vide.');
            return $this->redirectToRoute('panier_index');
        }

        $commande = (new Commande())
            ->setIdClient($userId)
            ->setStatut(Commande::STATUT_ATTENTE);
        $totalHt  = 0.0;

        foreach ($items as $item) {
            $produit = $produitRepo->find($item->getIdProduit());
            if (!$produit || !$produit->isDisponible()) continue;

            $prix  = (float) $produit->getPrix();
            $ligne = (new CommandeLigne())
                ->setCommande($commande)
                ->setIdProduit($item->getIdProduit())
                ->setQuantite($item->getQuantite())
                ->setPrixHtUnitaire(number_format($prix, 3, '.', ''))
                ->setTvaRate('19.00');

            $commande->addLigne($ligne);
            $em->persist($ligne);
            $totalHt += $prix * $item->getQuantite();
        }

        if ($commande->getLignes()->isEmpty()) {
            $this->addFlash('danger', 'Aucun produit disponible dans le panier.');
            return $this->redirectToRoute('panier_index');
        }

        $tva = $totalHt * 0.19;
        $commande
            ->setTotalHt(number_format($totalHt, 3, '.', ''))
            ->setTotalTva(number_format($tva, 3, '.', ''))
            ->setTotalTtc(number_format($totalHt + $tva, 3, '.', ''));

        $em->persist($commande);
        $panierRepo->viderPanier($userId);
        $em->flush();

        $this->addFlash('success', '✅ Commande #' . $commande->getIdCommande() . ' passée ! En attente de confirmation.');
        return $this->redirectToRoute('commande_index');
    }

    // ════════════════════════════════════════════════════════════════════
    //  COMMANDE DIRECTE (1 produit)
    // ════════════════════════════════════════════════════════════════════

    #[Route('/directe/{produitId}', name: 'directe', methods: ['POST'], requirements: ['produitId' => '\d+'])]
    public function directe(
        int $produitId,
        Request $request,
        ProduitServiceRepository $produitRepo,
        EntityManagerInterface $em
    ): Response {
        if ($r = $this->requireStartup()) return $r;

        $produit = $produitRepo->find($produitId);
        $qte     = max(1, (int) $request->request->get('quantite', 1));

        if (!$produit || !$produit->isDisponible()) {
            $this->addFlash('danger', 'Produit non disponible.');
            return $this->redirectToRoute('produit_index');
        }

        $prix  = (float) $produit->getPrix();
        $ht    = $prix * $qte;
        $tva   = $ht * 0.19;

        $ligne = (new CommandeLigne())
            ->setIdProduit($produitId)
            ->setQuantite($qte)
            ->setPrixHtUnitaire(number_format($prix, 3, '.', ''))
            ->setTvaRate('19.00');

        $commande = (new Commande())
            ->setIdClient($this->getUserId())
            ->setIdProduit($produitId)
            ->setQuantite($qte)
            ->setStatut(Commande::STATUT_ATTENTE)
            ->setTotalHt(number_format($ht, 3, '.', ''))
            ->setTotalTva(number_format($tva, 3, '.', ''))
            ->setTotalTtc(number_format($ht + $tva, 3, '.', ''));

        $commande->addLigne($ligne);
        $em->persist($commande);
        $em->persist($ligne);
        $em->flush();

        $this->addFlash('success', '⚡ Commande #' . $commande->getIdCommande() . ' créée !');
        return $this->redirectToRoute('commande_index');
    }

    // ════════════════════════════════════════════════════════════════════
    //  INVESTISSEUR — commandes reçues
    // ════════════════════════════════════════════════════════════════════

    #[Route('/investisseur/recues', name: 'investisseur_recues', methods: ['GET'])]
    public function recues(CommandeRepository $repo, ProduitServiceRepository $produitRepo, Request $request): Response
    {
        if ($r = $this->requireInvestisseur()) return $r;

        $statut      = $request->query->get('statut');
        $commandes   = $repo->findByInvestisseur($this->getUserId(), $statut);
        $toutesCmd   = $repo->findByInvestisseur($this->getUserId()); // unfiltered for KPIs

        $caTotal     = array_sum(array_map(fn($c) => (float)$c->getTotalTtc(), $toutesCmd));
        $caConfirmee = array_sum(array_map(fn($c) => $c->getStatut() === Commande::STATUT_CONFIRMEE ? (float)$c->getTotalTtc() : 0, $toutesCmd));
        $caLivree    = array_sum(array_map(fn($c) => $c->getStatut() === Commande::STATUT_LIVREE    ? (float)$c->getTotalTtc() : 0, $toutesCmd));
        $nbAttente   = count(array_filter($toutesCmd, fn($c) => $c->getStatut() === Commande::STATUT_ATTENTE));

        // Build product name map + sales totals for display and chart
        $produitsMap  = [];
        $ventesQte    = []; // id_produit => total qty sold (confirmed only)
        $ventesToutes = []; // id_produit => total qty (all statuts)

        foreach ($commandes as $cmd) {
            foreach ($cmd->getLignes() as $ligne) {
                $id = $ligne->getIdProduit();
                if (!isset($produitsMap[$id])) {
                    $p = $produitRepo->find($id);
                    $produitsMap[$id] = $p ? $p->getNom() : 'Produit #' . $id;
                }
                $ventesToutes[$id] = ($ventesToutes[$id] ?? 0) + $ligne->getQuantite();
                if ($cmd->getStatut() === Commande::STATUT_CONFIRMEE) {
                    $ventesQte[$id] = ($ventesQte[$id] ?? 0) + $ligne->getQuantite();
                }
            }
        }

        // Use all-statuts data for chart if confirmed sales are all zero
        $chartVentes = array_sum($ventesQte) > 0 ? $ventesToutes : $ventesToutes;
        arsort($chartVentes);

        $chartLabels = [];
        $chartValues = [];
        foreach ($chartVentes as $id => $qty) {
            $chartLabels[] = $produitsMap[$id] ?? ('Produit #' . $id);
            $chartValues[] = $qty;
        }

        return $this->render('front/marketplace/commandes/investisseur.html.twig', [
            'commandes'      => $commandes,
            'produits_map'   => $produitsMap,
            'statut_filtre'  => $statut,
            'chart_labels'   => $chartLabels,
            'chart_values'   => $chartValues,
            'kpi'            => [
                'ca_total'     => number_format($caTotal, 3, '.', ' '),
                'ca_confirmee' => number_format($caConfirmee, 3, '.', ' '),
                'ca_livree'    => number_format($caLivree, 3, '.', ' '),
                'nb_attente'   => $nbAttente,
                'nb_total'     => count($toutesCmd),
            ],
            'statuts_dispo'  => [
                Commande::STATUT_ATTENTE,
                Commande::STATUT_CONFIRMEE,
                Commande::STATUT_ANNULEE,
                Commande::STATUT_LIVREE,
            ],
        ]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  GENERIC PARAMETERIZED ROUTES (must come after specific routes)
    // ════════════════════════════════════════════════════════════════════

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Commande $commande, Request $request, ProduitServiceRepository $produitRepo): Response
    {
        $user = $this->getUser();

        // Startup: voir ses propres commandes
        if ($user->getUserType() === 'startup') {
            if ($commande->getIdClient() !== $this->getUserId()) {
                throw $this->createAccessDeniedException();
            }
        }
        // Investisseur: voir les commandes reçues pour ses produits
        elseif ($user->getUserType() === 'investisseur') {
            // Vérifier si au moins un produit de cette commande lui appartient
            $hasAccess = false;
            foreach ($commande->getLignes() as $ligne) {
                $produit = $produitRepo->find($ligne->getIdProduit());
                if ($produit && $produit->getOwnerUserId() === $this->getUserId()) {
                    $hasAccess = true;
                    break;
                }
            }
            if (!$hasAccess && $commande->getIdProduit()) {
                $produit = $produitRepo->find($commande->getIdProduit());
                if ($produit && $produit->getOwnerUserId() === $this->getUserId()) {
                    $hasAccess = true;
                }
            }
            if (!$hasAccess) {
                throw $this->createAccessDeniedException();
            }
        } else {
            throw $this->createAccessDeniedException();
        }

        $lignesDetail = [];
        foreach ($commande->getLignes() as $ligne) {
            $lignesDetail[] = [
                'ligne'   => $ligne,
                'produit' => $produitRepo->find($ligne->getIdProduit()),
            ];
        }

        return $this->render('front/marketplace/commandes/show.html.twig', [
            'commande'      => $commande,
            'lignes_detail' => $lignesDetail,
        ]);
    }

    #[Route('/{id}/annuler', name: 'annuler', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function annuler(Commande $commande, Request $request, EntityManagerInterface $em): Response
    {
        if ($r = $this->requireLogin()) return $r;
        if ($commande->getIdClient() !== $this->getUserId()) throw $this->createAccessDeniedException();

        if ($commande->getStatut() !== Commande::STATUT_ATTENTE) {
            $this->addFlash('warning', 'Seules les commandes en attente peuvent être annulées.');
            return $this->redirectToRoute('commande_show', ['id' => $commande->getIdCommande()]);
        }

        if ($this->isCsrfTokenValid('annuler_' . $commande->getIdCommande(), $request->request->get('_token'))) {
            $commande->setStatut(Commande::STATUT_ANNULEE);
            $em->flush();
            $this->addFlash('success', 'Commande #' . $commande->getIdCommande() . ' annulée.');
        }
        return $this->redirectToRoute('commande_index');
    }

    #[Route('/{id}/confirmer', name: 'confirmer', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function confirmer(
        Commande $commande,
        Request $request,
        EntityManagerInterface $em,
        ProduitServiceRepository $produitRepo
    ): Response {
        if ($r = $this->requireInvestisseur()) return $r;

        if (!$this->isCsrfTokenValid('confirmer_' . $commande->getIdCommande(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token invalide.');
            return $this->redirectToRoute('commande_investisseur_recues');
        }
        if ($commande->getStatut() !== Commande::STATUT_ATTENTE) {
            $this->addFlash('warning', 'Commande déjà traitée.');
            return $this->redirectToRoute('commande_investisseur_recues');
        }

        $commande->setStatut(Commande::STATUT_CONFIRMEE);

        // ── Décrémentation automatique du stock ──────────────────────────
        foreach ($commande->getLignes() as $ligne) {
            $produit = $produitRepo->find($ligne->getIdProduit());
            if ($produit) {
                $newQte = max(0, $produit->getQuantite() - $ligne->getQuantite());
                $produit->setQuantite($newQte);
                if ($newQte === 0) {
                    $produit->setDisponible(false);
                }
            }
        }

        $em->flush();
        $this->addFlash('success', '✔ Commande #' . $commande->getIdCommande() . ' confirmée. Stock mis à jour.');
        return $this->redirectToRoute('commande_investisseur_recues');
    }

    #[Route('/{id}/refuser', name: 'refuser', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function refuser(Commande $commande, Request $request, EntityManagerInterface $em): Response
    {
        if ($r = $this->requireInvestisseur()) return $r;

        if (!$this->isCsrfTokenValid('refuser_' . $commande->getIdCommande(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token invalide.');
            return $this->redirectToRoute('commande_investisseur_recues');
        }
        if ($commande->getStatut() !== Commande::STATUT_ATTENTE) {
            $this->addFlash('warning', 'Impossible de refuser une commande déjà traitée.');
            return $this->redirectToRoute('commande_investisseur_recues');
        }

        $commande->setStatut(Commande::STATUT_ANNULEE);
        $em->flush();
        $this->addFlash('success', '✖ Commande #' . $commande->getIdCommande() . ' refusée.');
        return $this->redirectToRoute('commande_investisseur_recues');
    }

    // ════════════════════════════════════════════════════════════════════
    //  MARQUER LIVRÉE — investisseur
    // ════════════════════════════════════════════════════════════════════

    #[Route('/{id}/livrer', name: 'livrer', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function livrer(Commande $commande, Request $request, EntityManagerInterface $em): Response
    {
        if ($r = $this->requireInvestisseur()) return $r;

        if (!$this->isCsrfTokenValid('livrer_' . $commande->getIdCommande(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token invalide.');
            return $this->redirectToRoute('commande_investisseur_recues');
        }
        if ($commande->getStatut() !== Commande::STATUT_CONFIRMEE) {
            $this->addFlash('warning', 'Seules les commandes confirmées peuvent être marquées comme livrées.');
            return $this->redirectToRoute('commande_investisseur_recues');
        }

        $commande->setStatut(Commande::STATUT_LIVREE);
        $em->flush();
        $this->addFlash('success', '🚚 Commande #' . $commande->getIdCommande() . ' marquée comme livrée.');
        return $this->redirectToRoute('commande_investisseur_recues');
    }

    // ════════════════════════════════════════════════════════════════════
    //  REORDER — startup rejoue une commande existante
    // ════════════════════════════════════════════════════════════════════

    #[Route('/{id}/reorder', name: 'reorder', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reorder(
        Commande $original,
        Request $request,
        ProduitServiceRepository $produitRepo,
        EntityManagerInterface $em
    ): Response {
        if ($r = $this->requireStartup()) return $r;
        if ($original->getIdClient() !== $this->getUserId()) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('reorder_' . $original->getIdCommande(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token invalide.');
            return $this->redirectToRoute('commande_index');
        }

        $nouvelle = (new Commande())
            ->setIdClient($this->getUserId())
            ->setStatut(Commande::STATUT_ATTENTE);

        $totalHt = 0.0;
        $hasLine = false;

        foreach ($original->getLignes() as $oldLigne) {
            $produit = $produitRepo->find($oldLigne->getIdProduit());
            if (!$produit || !$produit->isDisponible() || $produit->getQuantite() < 1) {
                continue;
            }
            $qte  = min($oldLigne->getQuantite(), $produit->getQuantite());
            $prix = (float) $produit->getPrix();

            $ligne = (new CommandeLigne())
                ->setIdProduit($oldLigne->getIdProduit())
                ->setQuantite($qte)
                ->setPrixHtUnitaire(number_format($prix, 3, '.', ''))
                ->setTvaRate('19.00');

            $nouvelle->addLigne($ligne);
            $em->persist($ligne);
            $totalHt += $prix * $qte;
            $hasLine = true;
        }

        if (!$hasLine) {
            $this->addFlash('warning', 'Aucun produit de cette commande n\'est disponible actuellement.');
            return $this->redirectToRoute('commande_index');
        }

        $tva = $totalHt * 0.19;
        $nouvelle
            ->setTotalHt(number_format($totalHt, 3, '.', ''))
            ->setTotalTva(number_format($tva, 3, '.', ''))
            ->setTotalTtc(number_format($totalHt + $tva, 3, '.', ''));

        $em->persist($nouvelle);
        $em->flush();

        $this->addFlash('success', '🔁 Commande #' . $nouvelle->getIdCommande() . ' créée depuis la commande #' . $original->getIdCommande() . '.');
        return $this->redirectToRoute('commande_index');
    }

    // ════════════════════════════════════════════════════════════════════
    //  EXPORT CSV — startup
    // ════════════════════════════════════════════════════════════════════

    #[Route('/export-csv', name: 'export_csv', methods: ['GET'])]
    public function exportCsv(CommandeRepository $repo, ProduitServiceRepository $produitRepo): StreamedResponse
    {
        if ($r = $this->requireStartup()) return $r;

        $commandes = $repo->findByClient($this->getUserId());

        $response = new StreamedResponse(function () use ($commandes, $produitRepo) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 pour Excel
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, ['N° Commande', 'Date', 'Produits', 'Quantité totale', 'Total HT', 'Total TVA', 'Total TTC', 'Statut'], ';');

            foreach ($commandes as $cmd) {
                $produits = [];
                $qteTotal = 0;
                foreach ($cmd->getLignes() as $ligne) {
                    $p = $produitRepo->find($ligne->getIdProduit());
                    $nom = $p ? $p->getNom() : 'Produit #' . $ligne->getIdProduit();
                    $produits[] = $nom . ' ×' . $ligne->getQuantite();
                    $qteTotal += $ligne->getQuantite();
                }
                fputcsv($handle, [
                    '#' . $cmd->getIdCommande(),
                    $cmd->getDateCommande()?->format('d/m/Y H:i') ?? '',
                    implode(' | ', $produits),
                    $qteTotal,
                    $cmd->getTotalHt() . ' TND',
                    $cmd->getTotalTva() . ' TND',
                    $cmd->getTotalTtc() . ' TND',
                    $cmd->getStatut(),
                ], ';');
            }
            fclose($handle);
        });

        $filename = 'commandes_' . date('Ymd_His') . '.csv';
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }
}
