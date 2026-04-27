<?php

namespace App\Event;

use App\Entity\Marketplace\Commande;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Déclenché après qu'une commande passe au statut "confirmee".
 * Consommé par CommandeConfirmeeListener (Twilio SMS, logs, etc.).
 */
class CommandeConfirmeeEvent extends Event
{
    public const NAME = 'commande.confirmee';

    public function __construct(
        private readonly Commande $commande,
        private readonly ?int $investisseurId = null,
    ) {}

    public function getCommande(): Commande
    {
        return $this->commande;
    }

    public function getInvestisseurId(): ?int
    {
        return $this->investisseurId;
    }
}
