<?php

namespace App\Service\Marketplace;

use App\Entity\Marketplace\Commande;

class CommandeManager
{
    private const STATUTS_AUTORISES = [
        'en_attente',
        'confirmee',
        'en_cours_paiement',
        'payee',
        'en_preparation',
        'annulee',
        'livree',
    ];

    public function validate(Commande $commande): bool
    {
        $quantite = $commande->getQuantite();
        if ($quantite < 1 || $quantite > 999) {
            throw new \InvalidArgumentException('La quantité doit être comprise entre 1 et 999');
        }

        $statut = $commande->getStatut();
        if (!in_array($statut, self::STATUTS_AUTORISES, true)) {
            throw new \InvalidArgumentException('Statut de commande invalide');
        }

        return true;
    }
}