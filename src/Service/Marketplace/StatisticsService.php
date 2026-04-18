<?php

namespace App\Service\Marketplace;

use App\Repository\Marketplace\CommandeRepository;
use App\Repository\Marketplace\CommandeLigneRepository;

/**
 * Service centralisant les statistiques marketplace.
 * Toutes les requêtes utilisent des QueryBuilders Doctrine optimisés
 * (agrégation côté base de données, pas de chargement d'entités complets).
 */
class StatisticsService
{
    public function __construct(
        private readonly CommandeRepository      $commandeRepository,
        private readonly CommandeLigneRepository $commandeLigneRepository,
    ) {}

    /**
     * Top N commandes par montant TTC décroissant.
     *
     * @return array<int, array{
     *   rank: int,
     *   idCommande: int,
     *   idClient: int,
     *   statut: string,
     *   dateCommande: string,
     *   totalTtc: float,
     *   totalHt: float,
     *   totalTva: float,
     * }>
     */
    public function getTopOrdersByAmount(int $limit = 5): array
    {
        $rows = $this->commandeRepository->findTopByAmount($limit);

        return array_map(
            static function (array $row, int $index): array {
                return [
                    'rank'          => $index + 1,
                    'idCommande'    => $row['idCommande'],
                    'idClient'      => $row['idClient'],
                    'statut'        => $row['statut'],
                    'dateCommande'  => $row['dateCommande'] instanceof \DateTimeInterface
                        ? $row['dateCommande']->format('Y-m-d H:i:s')
                        : (string) $row['dateCommande'],
                    'totalTtc'      => (float) ($row['totalTtc'] ?? 0),
                    'totalHt'       => (float) ($row['totalHt'] ?? 0),
                    'totalTva'      => (float) ($row['totalTva'] ?? 0),
                ];
            },
            $rows,
            array_keys($rows)
        );
    }

    /**
     * Top N produits les plus commandés (par quantité totale vendue).
     *
     * @return array<int, array{
     *   rank: int,
     *   idProduit: int,
     *   nomProduit: string,
     *   categorie: string|null,
     *   totalQuantite: int,
     *   totalRevenue: float,
     *   nbCommandes: int,
     * }>
     */
    public function getTopProductsByFrequency(int $limit = 5): array
    {
        $rows = $this->commandeLigneRepository->findTopProductsByFrequency($limit);

        return array_map(
            static function (array $row, int $index): array {
                return [
                    'rank'          => $index + 1,
                    'idProduit'     => (int) $row['idProduit'],
                    'nomProduit'    => $row['nomProduit'],
                    'categorie'     => $row['categorie'] ?? null,
                    'totalQuantite' => (int) $row['totalQuantite'],
                    'totalRevenue'  => round((float) $row['totalRevenue'], 3),
                    'nbCommandes'   => (int) $row['nbCommandes'],
                ];
            },
            $rows,
            array_keys($rows)
        );
    }

    /**
     * Top N produits par chiffre d'affaires (montant HT total généré).
     *
     * @return array<int, array{
     *   rank: int,
     *   idProduit: int,
     *   nomProduit: string,
     *   categorie: string|null,
     *   totalRevenue: float,
     *   totalQuantite: int,
     *   nbCommandes: int,
     * }>
     */
    public function getTopProductsByRevenue(int $limit = 5): array
    {
        $rows = $this->commandeLigneRepository->findTopProductsByRevenue($limit);

        return array_map(
            static function (array $row, int $index): array {
                return [
                    'rank'          => $index + 1,
                    'idProduit'     => (int) $row['idProduit'],
                    'nomProduit'    => $row['nomProduit'],
                    'categorie'     => $row['categorie'] ?? null,
                    'totalRevenue'  => round((float) $row['totalRevenue'], 3),
                    'totalQuantite' => (int) $row['totalQuantite'],
                    'nbCommandes'   => (int) $row['nbCommandes'],
                ];
            },
            $rows,
            array_keys($rows)
        );
    }

    /**
     * Chiffre d'affaires mensuel des N derniers mois (commandes payées seulement).
     *
     * @return array<int, array{mois: string, total: float, nb: int}>
     */
    public function getMonthlyRevenue(int $months = 6): array
    {
        $rows = $this->commandeRepository->findMonthlyRevenue($months);

        return array_map(static function (array $row): array {
            return [
                'mois'  => $row['mois'],
                'total' => round((float) ($row['total'] ?? 0), 3),
                'nb'    => (int) $row['nb'],
            ];
        }, $rows);
    }

    /**
     * Répartition des commandes par statut.
     *
     * @return array<int, array{statut: string, nb: int, totalTtc: float}>
     */
    public function getStatsByStatut(): array
    {
        $rows = $this->commandeRepository->findStatsByStatut();

        return array_map(static function (array $row): array {
            return [
                'statut'   => $row['statut'],
                'nb'       => (int) $row['nb'],
                'totalTtc' => round((float) ($row['totalTtc'] ?? 0), 3),
            ];
        }, $rows);
    }

    /**
     * Résumé global (KPIs) pour le dashboard marketplace.
     *
     * @return array{
     *   topOrdersByAmount: array,
     *   topProductsByFrequency: array,
     *   topProductsByRevenue: array,
     *   monthlyRevenue: array,
     *   statsByStatut: array,
     * }
     */
    public function getDashboardSummary(int $top = 5, int $months = 6): array
    {
        return [
            'topOrdersByAmount'      => $this->getTopOrdersByAmount($top),
            'topProductsByFrequency' => $this->getTopProductsByFrequency($top),
            'topProductsByRevenue'   => $this->getTopProductsByRevenue($top),
            'monthlyRevenue'         => $this->getMonthlyRevenue($months),
            'statsByStatut'          => $this->getStatsByStatut(),
        ];
    }
}
