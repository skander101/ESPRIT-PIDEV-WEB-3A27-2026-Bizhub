<?php

namespace App\Service\Marketplace;

use App\Entity\Marketplace\ProduitService;
use App\Repository\Marketplace\CommandeLigneRepository;
use App\Repository\Marketplace\ProduitServiceRepository;

/**
 * Moteur de recommandations marketplace.
 *
 * Trois stratégies utilisées, combinées par ordre de priorité :
 *   1. Similarité par catégorie  (pertinence sémantique)
 *   2. Historique utilisateur    (personnalisation)
 *   3. Popularité globale        (fallback universel)
 */
class RecommendationService
{
    public function __construct(
        private readonly ProduitServiceRepository $produitRepo,
        private readonly CommandeLigneRepository  $ligneRepo,
    ) {}

    // ────────────────────────────────────────────────────────────────────
    //  PAGE PRODUIT — "Produits similaires"
    // ────────────────────────────────────────────────────────────────────

    /**
     * Retourne jusqu'à $limit produits similaires au produit donné.
     *
     * Stratégie :
     *   - Priorité 1 : même catégorie, disponibles, excluant le produit courant.
     *   - Complété si nécessaire par les produits populaires (hors catégorie).
     *
     * @return ProduitService[]
     */
    public function getSimilarProducts(ProduitService $produit, int $limit = 4): array
    {
        $similaires = [];

        // 1. Même catégorie
        if ($produit->getCategorie()) {
            $similaires = $this->produitRepo->findByCategorieSauf(
                $produit->getCategorie(),
                (int) $produit->getIdProduit(),
                $limit
            );
        }

        // 2. Compléter avec des produits populaires si pas assez
        if (count($similaires) < $limit) {
            $deja = array_merge(
                [(int) $produit->getIdProduit()],
                array_map(fn($p) => $p->getIdProduit(), $similaires)
            );
            $popularIds = $this->ligneRepo->findPopularProductIds($limit * 2, $deja);
            $manquants  = $limit - count($similaires);

            if (!empty($popularIds)) {
                $fallback = $this->produitRepo->findDisponiblesExcluant(
                    array_merge($deja, array_diff($popularIds, $deja)),
                    $manquants
                );
                // On veut exactement les IDs populaires dans le bon ordre
                $fallback = $this->filterAndSortByIds($popularIds, $fallback, $manquants);
                $similaires = array_merge($similaires, $fallback);
            }
        }

        return array_slice($similaires, 0, $limit);
    }

    // ────────────────────────────────────────────────────────────────────
    //  PAGE PANIER — "Suggestions complémentaires"
    // ────────────────────────────────────────────────────────────────────

    /**
     * Retourne jusqu'à $limit suggestions pour le panier.
     *
     * Stratégie :
     *   - Exclut les produits déjà dans le panier.
     *   - Priorité 1 : même catégories que les articles du panier (cross-sell).
     *   - Priorité 2 : produits issus de l'historique d'achat de l'utilisateur.
     *   - Priorité 3 : produits populaires (fallback).
     *
     * @param array<int, array{produit: ProduitService|null}> $panierDetails
     * @return ProduitService[]
     */
    public function getCartSuggestions(array $panierDetails, ?int $userId = null, int $limit = 4): array
    {
        // IDs déjà dans le panier → à exclure
        $excludeIds = array_filter(array_map(
            fn($d) => $d['produit']?->getIdProduit(),
            $panierDetails
        ));
        $excludeIds = array_values(array_unique(array_map('intval', $excludeIds)));

        // Catégories présentes dans le panier
        $categories = array_unique(array_filter(array_map(
            fn($d) => $d['produit']?->getCategorie(),
            $panierDetails
        )));

        $suggestions = [];

        // 1. Même catégories (cross-sell)
        if (!empty($categories)) {
            $suggestions = $this->produitRepo->findByCategoriesExcluant(
                array_values($categories),
                $excludeIds,
                $limit
            );
        }

        // 2. Historique utilisateur en complément
        if ($userId && count($suggestions) < $limit) {
            $historyIds = $this->ligneRepo->findProductIdsByUser($userId);
            $deja = array_merge($excludeIds, array_map(fn($p) => $p->getIdProduit(), $suggestions));
            $nouveaux = array_diff($historyIds, $deja);

            if (!empty($nouveaux)) {
                $fromHistory = $this->produitRepo->findDisponiblesExcluant(
                    array_merge($deja, array_values($nouveaux)),
                    $limit - count($suggestions)
                );
                // On filtre pour ne garder que ceux dans $nouveaux
                $fromHistory = array_filter($fromHistory, fn($p) => in_array($p->getIdProduit(), $nouveaux, true));
                $suggestions = array_merge($suggestions, array_values($fromHistory));
            }
        }

        // 3. Fallback popularité
        if (count($suggestions) < $limit) {
            $deja       = array_merge($excludeIds, array_map(fn($p) => $p->getIdProduit(), $suggestions));
            $popularIds = $this->ligneRepo->findPopularProductIds($limit * 2, $deja);
            $manquants  = $limit - count($suggestions);

            if (!empty($popularIds)) {
                $fallback = $this->produitRepo->findDisponiblesExcluant(
                    array_merge($deja, array_diff($popularIds, $deja)),
                    $manquants
                );
                $fallback    = $this->filterAndSortByIds($popularIds, $fallback, $manquants);
                $suggestions = array_merge($suggestions, $fallback);
            }
        }

        return array_slice($suggestions, 0, $limit);
    }

    // ────────────────────────────────────────────────────────────────────
    //  HELPER PRIVÉ
    // ────────────────────────────────────────────────────────────────────

    /**
     * Filtre un tableau de ProduitService pour ne garder que ceux dont
     * l'ID figure dans $ids, et les trie dans l'ordre de $ids.
     *
     * @param int[]            $ids    Ordre de priorité souhaité
     * @param ProduitService[] $produits
     * @return ProduitService[]
     */
    private function filterAndSortByIds(array $ids, array $produits, int $limit): array
    {
        $indexed = [];
        foreach ($produits as $p) {
            $indexed[$p->getIdProduit()] = $p;
        }

        $result = [];
        foreach ($ids as $id) {
            if (isset($indexed[$id])) {
                $result[] = $indexed[$id];
                if (count($result) >= $limit) break;
            }
        }

        // Complète avec les produits non présents dans $ids si nécessaire
        if (count($result) < $limit) {
            foreach ($produits as $p) {
                if (!in_array($p->getIdProduit(), $ids, true)) {
                    $result[] = $p;
                    if (count($result) >= $limit) break;
                }
            }
        }

        return $result;
    }
}
