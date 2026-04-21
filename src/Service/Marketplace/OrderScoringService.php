<?php

namespace App\Service\Marketplace;

use App\Entity\Marketplace\Commande;
use App\Entity\UsersAvis\User;
use App\Repository\Marketplace\CommandeRepository;

/**
 * Moteur de scoring pour la confirmation automatique des commandes.
 *
 * Architecture évolutive : remplacer la méthode calculateScore() par un appel
 * à une API ML externe sans modifier les consommateurs (CommandeController).
 *
 * Résultat après pénalité de 10 points:
 *   score >= 50  → confirmation automatique (raw >= 60)
 *   score <= 40  → rejet automatique (raw <= 50)
 *   41–49        → attente validation manuelle (raw 51-59)
 */
class OrderScoringService
{
    // Seuils de décision (après pénalité de 10 points)
    private const SEUIL_AUTO_CONFIRM = 60; // 50 + 10 pénalité
    private const SEUIL_AUTO_REJECT  = 50; // 40 + 10 pénalité

    // Points de pénalité appliqués au score final
    private const PENALTY_POINTS = 10;

    // Poids des critères (total = 100)
    private const POIDS_MONTANT      = 20;
    private const POIDS_HISTORIQUE   = 30;
    private const POIDS_ANCIENNETE   = 20;
    private const POIDS_DISPONIBLE   = 15;
    private const POIDS_COMPLETUDE   = 15;

    public function __construct(
        private readonly CommandeRepository $commandeRepository,
    ) {}

    /**
     * Retourne le score détaillé avec la décomposition par critère.
     * Utilisé par l'interface IA pour afficher l'explication du score.
     *
     * @return array{score: int, decision: string, criteria: list<array{nom: string, score: int, max: int, icone: string, description: string}>}
     */
    public function getDetailedScore(Commande $commande, User $client): array
    {
        $ttc     = (float) $commande->getTotalTtc();
        $commandes = $this->commandeRepository->findByClient($client->getUserId());
        $nbConfirmees = count(array_filter(
            $commandes,
            fn(Commande $c) => in_array($c->getStatut(), [
                Commande::STATUT_CONFIRMEE,
                Commande::STATUT_PAYEE,
                Commande::STATUT_LIVREE,
            ], true)
        ));
        $createdAt = $client->getCreatedAt();
        $joursAnciennete = $createdAt ? (new \DateTime())->diff($createdAt)->days : 0;

        // — Critère 1 : Montant
        $sMontant = $this->scoreMontant($commande);
        $criteria[] = [
            'nom'         => 'Montant de la commande',
            'score'       => $sMontant,
            'max'         => self::POIDS_MONTANT,
            'icone'       => 'coins',
            'description' => match (true) {
                $ttc <= 500   => 'Montant faible — risque minimal.',
                $ttc <= 2000  => 'Montant modéré — risque acceptable.',
                $ttc <= 5000  => 'Montant élevé — attention requise.',
                $ttc <= 10000 => 'Montant très élevé — validation recommandée.',
                default       => 'Montant exceptionnel — analyse manuelle obligatoire.',
            },
        ];

        // — Critère 2 : Historique
        $sHistorique = $this->scoreHistorique($client);
        $criteria[] = [
            'nom'         => 'Historique client',
            'score'       => $sHistorique,
            'max'         => self::POIDS_HISTORIQUE,
            'icone'       => 'history',
            'description' => match (true) {
                $nbConfirmees >= 10 => $nbConfirmees . ' commandes confirmées — client très fiable.',
                $nbConfirmees >= 5  => $nbConfirmees . ' commandes confirmées — bon historique.',
                $nbConfirmees >= 2  => $nbConfirmees . ' commandes confirmées — historique en construction.',
                $nbConfirmees === 1 => 'Une seule commande confirmée — profil à surveiller.',
                default             => 'Première commande — aucun historique disponible.',
            },
        ];

        // — Critère 3 : Ancienneté
        $sAnciennete = $this->scoreAnciennete($client);
        $criteria[] = [
            'nom'         => 'Ancienneté du compte',
            'score'       => $sAnciennete,
            'max'         => self::POIDS_ANCIENNETE,
            'icone'       => 'calendar-check',
            'description' => match (true) {
                $joursAnciennete >= 365 => 'Compte de plus d\'un an — profil établi.',
                $joursAnciennete >= 180 => 'Compte de plus de 6 mois — profil stable.',
                $joursAnciennete >= 90  => 'Compte de plus de 3 mois — en cours d\'établissement.',
                $joursAnciennete >= 30  => 'Compte récent — moins d\'un mois d\'ancienneté.',
                default                 => 'Compte très récent — création il y a moins de 30 jours.',
            },
        ];

        // — Critère 4 : Disponibilité
        $criteria[] = [
            'nom'         => 'Disponibilité des produits',
            'score'       => self::POIDS_DISPONIBLE,
            'max'         => self::POIDS_DISPONIBLE,
            'icone'       => 'boxes',
            'description' => 'Tous les produits commandés sont disponibles en stock.',
        ];

        // — Critère 5 : Complétude du profil
        $sCompletude = $this->scoreCompletude($client);
        $manquant = [];
        if (empty($client->getPhone()))       $manquant[] = 'téléphone';
        if (empty($client->getAddress()))     $manquant[] = 'adresse';
        if (empty($client->getCompanyName())) $manquant[] = 'nom société';
        $criteria[] = [
            'nom'         => 'Complétude du profil',
            'score'       => $sCompletude,
            'max'         => self::POIDS_COMPLETUDE,
            'icone'       => 'user-check',
            'description' => empty($manquant)
                ? 'Profil complet — toutes les informations renseignées.'
                : 'Informations manquantes : ' . implode(', ', $manquant) . '.',
        ];

        $total = min(100, max(0, array_sum(array_column($criteria, 'score'))));

        return [
            'score'    => $total,
            'decision' => $this->decide($total),
            'criteria' => $criteria,
        ];
    }

    /**
     * Calcule un score entre 0 et 100 pour une commande.
     */
    public function calculateScore(Commande $commande, User $client): int
    {
        $score = 0;

        $score += $this->scoreMontant($commande);
        $score += $this->scoreHistorique($client);
        $score += $this->scoreAnciennete($client);
        $score += $this->scoreDisponibilite($commande);
        $score += $this->scoreCompletude($client);

        return min(100, max(0, $score));
    }

    /**
     * Détermine la décision automatique selon le score.
     *
     * @return string 'auto_confirm' | 'auto_reject' | 'manual'
     */
    public function decide(int $score): string
    {
        if ($score >= self::SEUIL_AUTO_CONFIRM) {
            return 'auto_confirm';
        }
        if ($score <= self::SEUIL_AUTO_REJECT) {
            return 'auto_reject';
        }
        return 'manual';
    }

    // ── Critère 1 : montant de la commande ──────────────────────────────
    // Petits montants = moins risqué = score élevé
    private function scoreMontant(Commande $commande): int
    {
        $ttc = (float) $commande->getTotalTtc();

        if ($ttc <= 500) {
            return self::POIDS_MONTANT;        // 20/20
        } elseif ($ttc <= 2000) {
            return (int) (self::POIDS_MONTANT * 0.75); // 15/20
        } elseif ($ttc <= 5000) {
            return (int) (self::POIDS_MONTANT * 0.5);  // 10/20
        } elseif ($ttc <= 10000) {
            return (int) (self::POIDS_MONTANT * 0.25); // 5/20
        }
        return 0;
    }

    // ── Critère 2 : historique des commandes confirmées ─────────────────
    // Plus l'utilisateur a de commandes confirmées, plus il est fiable
    private function scoreHistorique(User $client): int
    {
        $commandes = $this->commandeRepository->findByClient($client->getUserId());
        $nbConfirmees = count(array_filter(
            $commandes,
            fn(Commande $c) => $c->getStatut() === Commande::STATUT_CONFIRMEE
                            || $c->getStatut() === Commande::STATUT_PAYEE
                            || $c->getStatut() === Commande::STATUT_LIVREE
        ));

        if ($nbConfirmees >= 10) {
            return self::POIDS_HISTORIQUE;           // 30/30
        } elseif ($nbConfirmees >= 5) {
            return (int) (self::POIDS_HISTORIQUE * 0.7);  // 21/30
        } elseif ($nbConfirmees >= 2) {
            return (int) (self::POIDS_HISTORIQUE * 0.4);  // 12/30
        } elseif ($nbConfirmees === 1) {
            return (int) (self::POIDS_HISTORIQUE * 0.2);  // 6/30
        }
        return 0; // première commande
    }

    // ── Critère 3 : ancienneté du compte ────────────────────────────────
    private function scoreAnciennete(User $client): int
    {
        $createdAt = $client->getCreatedAt();
        if (!$createdAt) {
            return 0;
        }

        $joursDepuisCreation = (new \DateTime())->diff($createdAt)->days;

        if ($joursDepuisCreation >= 365) {
            return self::POIDS_ANCIENNETE;            // 20/20
        } elseif ($joursDepuisCreation >= 180) {
            return (int) (self::POIDS_ANCIENNETE * 0.75);
        } elseif ($joursDepuisCreation >= 90) {
            return (int) (self::POIDS_ANCIENNETE * 0.5);
        } elseif ($joursDepuisCreation >= 30) {
            return (int) (self::POIDS_ANCIENNETE * 0.25);
        }
        return 0;
    }

    // ── Critère 4 : disponibilité stock (toutes les lignes en stock) ────
    private function scoreDisponibilite(Commande $commande): int
    {
        // Si la commande a passé la validation du contrôleur, les produits étaient disponibles
        // On accorde le score plein — le contrôleur vérifie déjà le stock
        return self::POIDS_DISPONIBLE; // 15/15
    }

    // ── Critère 5 : complétude du profil ────────────────────────────────
    private function scoreCompletude(User $client): int
    {
        $score = 0;
        $poids = self::POIDS_COMPLETUDE / 3;

        if (!empty($client->getPhone())) {
            $score += $poids;
        }
        if (!empty($client->getAddress())) {
            $score += $poids;
        }
        if (!empty($client->getCompanyName())) {
            $score += $poids;
        }

        return (int) $score;
    }
}
