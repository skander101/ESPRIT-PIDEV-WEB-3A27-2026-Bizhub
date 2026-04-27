<?php

namespace App\Tests\Service\Elearning;

use App\Entity\Elearning\Formation;
use App\Service\Elearning\FormationManager;
use PHPUnit\Framework\TestCase;

class FormationManagerTest extends TestCase
{
    private FormationManager $manager;

    protected function setUp(): void
    {
        $this->manager = new FormationManager();
    }

    public function testFormationPresentielleValide(): void
    {
        $formation = new Formation();
        $formation->setEnLigne(false);
        $formation->setLieu('Tunis');
        $formation->setLatitude(36.8);
        $formation->setLongitude(10.1);
        $formation->setStartDate(new \DateTime('2026-05-01'));
        $formation->setEndDate(new \DateTime('2026-06-01'));

        $result = $this->manager->validate($formation);

        $this->assertTrue($result === true);
    }

    public function testFormationEnLigneValide(): void
    {
        $formation = new Formation();
        $formation->setEnLigne(true);
        $formation->setStartDate(new \DateTime('2026-05-01'));
        $formation->setEndDate(new \DateTime('2026-06-01'));

        $result = $this->manager->validate($formation);

        $this->assertTrue($result === true);
    }

    public function testFormationPresentiellesSansLieu(): void
    {
        $formation = new Formation();
        $formation->setEnLigne(false);
        $formation->setLieu('');
        $formation->setLatitude(36.8);
        $formation->setLongitude(10.1);
        $formation->setStartDate(new \DateTime('2026-05-01'));
        $formation->setEndDate(new \DateTime('2026-06-01'));

        $this->expectException(\InvalidArgumentException::class);
        $this->manager->validate($formation);
    }

    public function testFormationPresentiellesSansCoordonnees(): void
    {
        $formation = new Formation();
        $formation->setEnLigne(false);
        $formation->setLieu('Tunis');
        $formation->setLatitude(null);
        $formation->setLongitude(10.1);
        $formation->setStartDate(new \DateTime('2026-05-01'));
        $formation->setEndDate(new \DateTime('2026-06-01'));

        $this->expectException(\InvalidArgumentException::class);
        $this->manager->validate($formation);
    }

    public function testDateFinAvantDateDebut(): void
    {
        $formation = new Formation();
        $formation->setEnLigne(true);
        $formation->setStartDate(new \DateTime('2026-06-01'));
        $formation->setEndDate(new \DateTime('2026-05-01'));

        $this->expectException(\InvalidArgumentException::class);
        $this->manager->validate($formation);
    }
}