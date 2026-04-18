<?php

namespace App\Controller\Marketplace;

use App\Entity\Marketplace\Commande;
use App\Repository\Marketplace\CommandeRepository;
use App\Repository\Marketplace\CommandeStatusHistoryRepository;
use App\Repository\Marketplace\ProduitServiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Suivi des commandes pour la startup.
 * Réutilise CommandeRepository::findByClient() déjà en place.
 */
#[Route('/marketplace/tracking', name: 'tracking_')]
class TrackingController extends AbstractController
{
    private function requireStartup(): ?Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        if ($user->getUserType() !== 'startup') {
            $this->addFlash('error', 'Accès réservé aux startups.');
            return $this->redirectToRoute('produit_index');
        }
        return null;
    }

    // ════════════════════════════════════════════════════════════════════
    //  LISTE DES COMMANDES AVEC FILTRES
    // ════════════════════════════════════════════════════════════════════

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        CommandeRepository $commandeRepo,
        ProduitServiceRepository $produitRepo,
        Request $request,
    ): Response {
        if ($r = $this->requireStartup()) return $r;

        $userId  = (int) $this->getUser()->getUserId();
        $statut  = $request->query->get('statut');
        $search  = $request->query->get('q', '');
        $toutes  = $commandeRepo->findByClient($userId);

        // Filtrage : 'confirmee' regroupe aussi payee et en_cours_paiement (workflow display unifié)
        $commandes = $statut
            ? array_values(array_filter($toutes, function ($c) use ($statut) {
                $es = $c->getEffectiveStatut();
                if ($statut === Commande::STATUT_CONFIRMEE) {
                    return in_array($es, [
                        Commande::STATUT_CONFIRMEE,
                        Commande::STATUT_EN_COURS_PAIEMENT,
                        Commande::STATUT_PAYEE,
                    ], true) || $c->getStatut() === Commande::STATUT_EN_COURS_PAIEMENT;
                }
                return $es === $statut;
            }))
            : $toutes;

        // Filtrage par numéro de commande
        if ($search !== '') {
            $commandes = array_values(array_filter(
                $commandes,
                fn($c) => str_contains((string) $c->getIdCommande(), $search)
            ));
        }

        // Statistiques
        $stats = $this->buildStats($toutes);

        // Enrichissement avec noms de produits
        $commandesEnrichies = [];
        foreach ($commandes as $cmd) {
            $produitsNoms = [];
            foreach ($cmd->getLignes() as $ligne) {
                $p = $produitRepo->find($ligne->getIdProduit());
                $produitsNoms[] = $p ? $p->getNom() : 'Produit #' . $ligne->getIdProduit();
            }
            $effectif = $cmd->getEffectiveStatut();
            $commandesEnrichies[] = [
                'commande'      => $cmd,
                'produits_noms' => $produitsNoms,
                'badge_class'   => $this->getStatutBadgeClass($effectif),
                'icone'         => $this->getStatutIcone($effectif),
            ];
        }

        return $this->render('front/Marketplace/tracking/index.html.twig', [
            'commandes_enrichies' => $commandesEnrichies,
            'statut_filtre'       => $statut,
            'search'              => $search,
            'stats'               => $stats,
            'statuts_dispo'       => $this->getAllStatuts(),
        ]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  DÉTAIL + TIMELINE D'UNE COMMANDE
    // ════════════════════════════════════════════════════════════════════

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(
        Commande $commande,
        CommandeStatusHistoryRepository $historyRepo,
        ProduitServiceRepository $produitRepo,
    ): Response {
        if ($r = $this->requireStartup()) return $r;

        if ($commande->getIdClient() !== (int) $this->getUser()->getUserId()) {
            throw $this->createAccessDeniedException();
        }

        $historique = $historyRepo->findByCommande($commande);

        $lignesDetail = [];
        foreach ($commande->getLignes() as $ligne) {
            $lignesDetail[] = [
                'ligne'   => $ligne,
                'produit' => $produitRepo->find($ligne->getIdProduit()),
            ];
        }

        // Calcul de la progression dans le workflow
        $effectif = $commande->getEffectiveStatut();
        $etapes   = $this->buildWorkflowEtapes($effectif);

        return $this->render('front/Marketplace/tracking/show.html.twig', [
            'commande'      => $commande,
            'historique'    => $historique,
            'lignes_detail' => $lignesDetail,
            'etapes'        => $etapes,
            'badge_class'   => $this->getStatutBadgeClass($effectif),
        ]);
    }

    // ── Helpers privés ──────────────────────────────────────────────────

    private function buildStats(array $toutes): array
    {
        $stats = ['total' => count($toutes)];
        foreach ($this->getAllStatuts() as $s) {
            $stats[$s] = 0;
        }
        foreach ($toutes as $c) {
            $es = $c->getEffectiveStatut();
            // payee + en_cours_paiement sont regroupés sous confirmee (workflow display unifié)
            if (in_array($es, [
                    Commande::STATUT_EN_COURS_PAIEMENT,
                    Commande::STATUT_PAYEE,
                ], true) || $c->getStatut() === Commande::STATUT_EN_COURS_PAIEMENT
            ) {
                $stats[Commande::STATUT_CONFIRMEE] = ($stats[Commande::STATUT_CONFIRMEE] ?? 0) + 1;
            } elseif (isset($stats[$es])) {
                $stats[$es]++;
            }
        }
        return $stats;
    }

    private function getAllStatuts(): array
    {
        // payee et en_cours_paiement sont absents : regroupés sous 'confirmee' dans stats et filtres
        return [
            Commande::STATUT_ATTENTE,
            Commande::STATUT_CONFIRMEE,
            Commande::STATUT_EN_PREPARATION,
            Commande::STATUT_LIVREE,
            Commande::STATUT_ANNULEE,
        ];
    }

    private function getStatutBadgeClass(string $statut): string
    {
        return match ($statut) {
            Commande::STATUT_ATTENTE           => 'bg-warning text-dark',
            Commande::STATUT_CONFIRMEE         => 'bg-info text-dark',
            Commande::STATUT_EN_COURS_PAIEMENT => 'bg-primary',
            Commande::STATUT_PAYEE             => 'bg-success',
            Commande::STATUT_EN_PREPARATION    => 'bg-secondary',
            Commande::STATUT_LIVREE            => 'bg-success',
            Commande::STATUT_ANNULEE           => 'bg-danger',
            default                            => 'bg-secondary',
        };
    }

    private function getStatutIcone(string $statut): string
    {
        // Font Awesome 6 (fa fa-*) — chargé dans base_marketplace.html.twig
        return match ($statut) {
            Commande::STATUT_ATTENTE           => 'hourglass-half',
            Commande::STATUT_CONFIRMEE         => 'check-circle',
            Commande::STATUT_EN_COURS_PAIEMENT => 'credit-card',
            Commande::STATUT_PAYEE             => 'coins',
            Commande::STATUT_EN_PREPARATION    => 'box-open',
            Commande::STATUT_LIVREE            => 'truck',
            Commande::STATUT_ANNULEE           => 'times-circle',
            default                            => 'circle',
        };
    }

    /**
     * Construit les étapes du workflow pour la timeline Twig.
     * Chaque étape indique si elle est complétée, active ou future.
     */
    private function buildWorkflowEtapes(string $statutActuel): array
    {
        $ordre = [
            Commande::STATUT_ATTENTE,
            Commande::STATUT_CONFIRMEE,
            Commande::STATUT_EN_COURS_PAIEMENT,
            Commande::STATUT_PAYEE,
            Commande::STATUT_EN_PREPARATION,
            Commande::STATUT_LIVREE,
        ];

        $labels = [
            Commande::STATUT_ATTENTE           => 'En attente',
            Commande::STATUT_CONFIRMEE         => 'Confirmée',
            Commande::STATUT_EN_COURS_PAIEMENT => 'Paiement en cours',
            Commande::STATUT_PAYEE             => 'Payée',
            Commande::STATUT_EN_PREPARATION    => 'En préparation',
            Commande::STATUT_LIVREE            => 'Livrée',
        ];

        $indexActuel = array_search($statutActuel, $ordre);

        $etapes = [];
        foreach ($ordre as $i => $statut) {
            $etapes[] = [
                'statut'     => $statut,
                'label'      => $labels[$statut] ?? $statut,
                'completed'  => $indexActuel !== false && $i < $indexActuel,
                'active'     => $statut === $statutActuel,
                'future'     => $indexActuel !== false && $i > $indexActuel,
                'icone'      => $this->getStatutIcone($statut),
            ];
        }

        return $etapes;
    }
}
