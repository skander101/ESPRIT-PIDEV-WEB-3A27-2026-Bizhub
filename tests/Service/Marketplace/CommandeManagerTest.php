<?php

namespace App\Tests\Service\Marketplace;

use App\Entity\Marketplace\Commande;
use App\Service\Marketplace\CommandeManager;
use PHPUnit\Framework\TestCase;

class CommandeManagerTest extends TestCase
{
    private CommandeManager $manager;

    protected function setUp(): void
    {
        $this->manager = new CommandeManager();
    }

    public function testCommandeValide(): void
    {
        $commande = new Commande();
        $commande->setQuantite(5);
        $commande->setStatut('en_attente');

        $result = $this->manager->validate($commande);

        $this->assertTrue($result === true);
    }

    public function testQuantiteTropFaible(): void
    {
        $commande = new Commande();
        $commande->setQuantite(0);
        $commande->setStatut('en_attente');

        $this->expectException(\InvalidArgumentException::class);
        $this->manager->validate($commande);
    }

    public function testQuantiteTropElevee(): void
    {
        $commande = new Commande();
        $commande->setQuantite(1000);
        $commande->setStatut('en_attente');

        $this->expectException(\InvalidArgumentException::class);
        $this->manager->validate($commande);
    }

    public function testStatutInvalide(): void
    {
        $commande = new Commande();
        $commande->setQuantite(5);
        $commande->setStatut('invalide');

        $this->expectException(\InvalidArgumentException::class);
        $this->manager->validate($commande);
    }
}