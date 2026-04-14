<?php

namespace App\Controller\Admin\Marketplace;

use App\Entity\Marketplace\Commande;
use App\Entity\Marketplace\ProduitService;
use App\Form\Marketplace\CommandeType;
use App\Form\Marketplace\ProduitType;
use App\Repository\Marketplace\CommandeRepository;
use App\Repository\Marketplace\PanierRepository;
use App\Repository\Marketplace\ProduitServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
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
    ): Response {
        $toutesCommandes = $commandeRepo->findAll();
        return $this->render('back/marketplace/dashboard.html.twig', [
            'stats' => [
                'total_produits'  => count($produitRepo->findAll()),
                'produits_dispo'  => count($produitRepo->findDisponibles()),
                'total_commandes' => count($toutesCommandes),
                'en_attente'      => count(array_filter($toutesCommandes, fn($c) => $c->getStatut() === Commande::STATUT_ATTENTE)),
                'confirmees'      => count(array_filter($toutesCommandes, fn($c) => $c->getStatut() === Commande::STATUT_CONFIRMEE)),
                'paniers_actifs'  => count($panierRepo->findAll()),
            ],
            'statuts'           => $commandeRepo->countByStatut(),
            'derniers_produits' => $produitRepo->findAll(),
        ]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  PRODUITS — CRUD
    // ════════════════════════════════════════════════════════════════════

    #[Route('/produits', name: 'produits_index', methods: ['GET'])]
    public function produitsIndex(ProduitServiceRepository $repo, Request $request): Response
    {
        $cat = $request->query->get('categorie');
        return $this->render('back/marketplace/produits/index.html.twig', [
            'produits'   => $cat ? $repo->findByCategorie($cat) : $repo->findAll(),
            'categories' => $repo->findAllCategories(),
            'cat_active' => $cat,
        ]);
    }

    #[Route('/produits/new', name: 'produits_new', methods: ['GET', 'POST'])]
    public function produitsNew(Request $request, EntityManagerInterface $em): Response
    {
        $produit = new ProduitService();
        $form    = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($produit);
            $em->flush();
            $this->addFlash('success', '✅ Produit «' . $produit->getNom() . '» ajouté.');
            return $this->redirectToRoute('admin_marketplace_produits_index');
        }
        return $this->render('back/marketplace/produits/form.html.twig', [
            'form'  => $form->createView(),
            'titre' => 'Nouveau produit',
            'mode'  => 'create',
        ]);
    }

    #[Route('/produits/{id}/show', name: 'produits_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function produitsShow(ProduitService $produit): Response
    {
        return $this->render('back/marketplace/produits/show.html.twig', ['produit' => $produit]);
    }

    #[Route('/produits/{id}/edit', name: 'produits_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function produitsEdit(ProduitService $produit, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', '✏️ «' . $produit->getNom() . '» modifié.');
            return $this->redirectToRoute('admin_marketplace_produits_index');
        }
        return $this->render('back/marketplace/produits/form.html.twig', [
            'form'    => $form->createView(),
            'titre'   => 'Modifier — ' . $produit->getNom(),
            'produit' => $produit,
            'mode'    => 'edit',
        ]);
    }

    #[Route('/produits/{id}/delete', name: 'produits_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function produitsDelete(ProduitService $produit, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_produit_' . $produit->getIdProduit(), $request->request->get('_token'))) {
            $nom = $produit->getNom();
            $em->remove($produit);
            $em->flush();
            $this->addFlash('success', '🗑️ «' . $nom . '» supprimé.');
        }
        return $this->redirectToRoute('admin_marketplace_produits_index');
    }

    #[Route('/produits/{id}/toggle', name: 'produits_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function produitsToggle(ProduitService $produit, EntityManagerInterface $em): Response
    {
        $produit->setDisponible(!$produit->isDisponible());
        $em->flush();
        $this->addFlash('success', '«' . $produit->getNom() . '» → ' . ($produit->isDisponible() ? 'disponible' : 'indisponible'));
        return $this->redirectToRoute('admin_marketplace_produits_index');
    }

    // ════════════════════════════════════════════════════════════════════
    //  COMMANDES
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

    #[Route('/commandes/{id}/edit', name: 'commandes_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function commandesEdit(Commande $commande, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CommandeType::class, $commande, ['is_admin' => true]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Commande #' . $commande->getIdCommande() . ' mise à jour.');
            return $this->redirectToRoute('admin_marketplace_commandes_index');
        }
        return $this->render('back/marketplace/commandes/form.html.twig', [
            'form'     => $form->createView(),
            'commande' => $commande,
        ]);
    }

    #[Route('/commandes/{id}/statut/{statut}', name: 'commandes_statut', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function commandesStatut(Commande $commande, string $statut, Request $request, EntityManagerInterface $em): Response
    {
        $allowed = [Commande::STATUT_ATTENTE, Commande::STATUT_CONFIRMEE, Commande::STATUT_ANNULEE, Commande::STATUT_LIVREE];
        if ($this->isCsrfTokenValid('statut_' . $commande->getIdCommande(), $request->request->get('_token'))
            && in_array($statut, $allowed, true)) {
            $commande->setStatut($statut);
            $em->flush();
            $this->addFlash('success', 'Statut → «' . $statut . '»');
        }
        return $this->redirectToRoute('admin_marketplace_commandes_show', ['id' => $commande->getIdCommande()]);
    }

    #[Route('/commandes/{id}/delete', name: 'commandes_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function commandesDelete(Commande $commande, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_commande_' . $commande->getIdCommande(), $request->request->get('_token'))) {
            $id = $commande->getIdCommande();
            $em->remove($commande);
            $em->flush();
            $this->addFlash('success', '🗑️ Commande #' . $id . ' supprimée.');
        }
        return $this->redirectToRoute('admin_marketplace_commandes_index');
    }

    // ════════════════════════════════════════════════════════════════════
    //  PANIERS (lecture seule)
    // ════════════════════════════════════════════════════════════════════

    #[Route('/paniers', name: 'paniers_index', methods: ['GET'])]
    public function paniersIndex(PanierRepository $repo): Response
    {
        return $this->render('back/marketplace/paniers/index.html.twig', ['paniers' => $repo->findAll()]);
    }
}
