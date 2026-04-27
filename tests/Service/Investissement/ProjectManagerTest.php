<?php

namespace App\Tests\Service\Investissement;

use App\Entity\Investissement\Project;
use App\Service\Investissement\ProjectManager;
use PHPUnit\Framework\TestCase;

class ProjectManagerTest extends TestCase
{
    private ProjectManager $manager;

    protected function setUp(): void
    {
        $this->manager = new ProjectManager();
    }

    public function testProjectValide(): void
    {
        $project = new Project();
        $project->setRequiredBudget(50000);
        $project->setStatus('pending');

        $result = $this->manager->validate($project);

        $this->assertTrue($result === true);
    }

    public function testBudgetNul(): void
    {
        $project = new Project();
        $project->setRequiredBudget(0);
        $project->setStatus('pending');

        $this->expectException(\InvalidArgumentException::class);
        $this->manager->validate($project);
    }

    public function testBudgetNegatif(): void
    {
        $project = new Project();
        $project->setRequiredBudget(-100);
        $project->setStatus('pending');

        $this->expectException(\InvalidArgumentException::class);
        $this->manager->validate($project);
    }

    public function testStatutInvalide(): void
    {
        $project = new Project();
        $project->setRequiredBudget(50000);
        $project->setStatus('annule');

        $this->expectException(\InvalidArgumentException::class);
        $this->manager->validate($project);
    }
}