<?php

namespace App\Controller\Admin\Marketplace;

use App\Entity\Marketplace\Commande;
use App\Entity\Marketplace\ProduitService;
use App\Repository\Marketplace\CommandeRepository;
use App\Repository\Marketplace\PanierRepository;
use App\Repository\Marketplace\ProduitServiceRepository;
use App\Repository\UsersAvis\UserRepository;
use App\Service\Marketplace\StatisticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/back/marketplace', name: 'admin_marketplace_')]
class AdminMarketplaceController extends AbstractController
{
    // ════════════════════════════════════════════════════════════════════
    //  DASHBOARD
    // ════════════════════════════════════════════════════════════════════

    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function dashboard(
        ProduitServiceRepository $produitRepo,
        CommandeRepository $commandeRepo,
        PanierRepository $panierRepo,
        StatisticsService $statisticsService,
    ): Response {
        $toutesCommandes = $commandeRepo->findAll();

        // Revenue chart data (last 6 months)
        $monthlyRevenue  = $statisticsService->getMonthlyRevenue(6);
        $revenueLabels   = array_column($monthlyRevenue, 'mois');
        $revenueData     = array_column($monthlyRevenue, 'total');

        return $this->render('back/marketplace/dashboard.html.twig', [
            'stats' => [
                'total_produits'  => count($produitRepo->findAll()),
                'produits_dispo'  => count($produitRepo->findDisponibles()),
                'total_commandes' => count($toutesCommandes),
                'en_attente'      => count(array_filter($toutesCommandes, fn(Commande $c) => $c->getStatut() === Commande::STATUT_ATTENTE)),
                'confirmees'      => count(array_filter($toutesCommandes, fn(Commande $c) => $c->getStatut() === Commande::STATUT_CONFIRMEE)),
                'paniers_actifs'  => count($panierRepo->findAll()),
            ],
            'statuts'              => $commandeRepo->countByStatut(),
            'derniers_produits'    => $produitRepo->findAll(),
            'top_commandes'        => $statisticsService->getTopOrdersByAmount(5),
            'top_produits'         => $statisticsService->getTopProductsByFrequency(5),
            'revenue_labels'       => $revenueLabels,
            'revenue_data'         => $revenueData,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  PRODUITS — CRUD
    // ════════════════════════════════════════════════════════════════════

    #[Route('/produits', name: 'produits_index', methods: ['GET'])]
    public function produitsIndex(ProduitServiceRepository $repo, Request $request): Response
    {
        $cat = $request->query->get('categorie', '', 'string');
        return $this->render('back/marketplace/produits/index.html.twig', [
            'produits'   => $cat ? $repo->findByCategorie($cat) : $repo->findAll(),
            'categories' => $repo->findAllCategories(),
            'cat_active' => $cat,
        ]);
    }

    #[Route('/produits/{id}/show', name: 'produits_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function produitsShow(ProduitService $produit): Response
    {
        return $this->render('back/marketplace/produits/show.html.twig', ['produit' => $produit]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  COMMANDES (lecture seule)
    // ════════════════════════════════════════════════════════════════════

    #[Route('/commandes', name: 'commandes_index', methods: ['GET'])]
    public function commandesIndex(CommandeRepository $repo, Request $request): Response
    {
        $statut = $request->query->get('statut');
        return $this->render('back/marketplace/commandes/index.html.twig', [
            'commandes'     => $statut ? $repo->findByStatut($statut) : $repo->findAll(),
            'statut_filtre' => $statut,
            'statuts_dispo' => [Commande::STATUT_ATTENTE, Commande::STATUT_CONFIRMEE, Commande::STATUT_ANNULEE, Commande::STATUT_LIVREE],
        ]);
    }

    #[Route('/commandes/{id}/show', name: 'commandes_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function commandesShow(Commande $commande): Response
    {
        return $this->render('back/marketplace/commandes/show.html.twig', ['commande' => $commande]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  PANIERS (lecture seule)
    // ════════════════════════════════════════════════════════════════════

    #[Route('/paniers', name: 'paniers_index', methods: ['GET'])]
    public function paniersIndex(PanierRepository $repo): Response
    {
        return $this->render('back/marketplace/paniers/index.html.twig', ['paniers' => $repo->findAll()]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  CLIENTS
    // ════════════════════════════════════════════════════════════════════

    #[Route('/clients', name: 'clients_index', methods: ['GET'])]
    public function clientsIndex(
        UserRepository     $userRepo,
        CommandeRepository $commandeRepo,
        Request            $request,
    ): Response {
        $search   = trim((string) $request->query->get('q', ''));
        $typeFilter = $request->query->get('type', '');

        // Récupérer tous les utilisateurs (filtrés si recherche)
        $users = $search
            ? $userRepo->findBySearchAndSort($search)
            : $userRepo->findAll();

        // Filtrer par type si demandé
        if ($typeFilter !== '') {
            $users = array_filter($users, fn($u) => $u->getUserType() === $typeFilter);
        }

        // Nombre de commandes par client (indexed by idClient)
        /** @var Commande[] $allCommandes */
        $allCommandes     = $commandeRepo->findAll();
        $commandesParClient = [];
        foreach ($allCommandes as $cmd) {
            $id = $cmd->getIdClient();
            $commandesParClient[$id] = ($commandesParClient[$id] ?? 0) + 1;
        }

        // KPIs
        $distribution = $userRepo->getRoleDistribution();

        return $this->render('back/marketplace/clients/index.html.twig', [
            'users'                => array_values($users),
            'commandes_par_client' => $commandesParClient,
            'distribution'         => $distribution,
            'search'               => $search,
            'type_filter'          => $typeFilter,
            'types'                => ['startup', 'fournisseur', 'formateur', 'investisseur'],
            'total'                => count($users),
        ]);
    }
}
