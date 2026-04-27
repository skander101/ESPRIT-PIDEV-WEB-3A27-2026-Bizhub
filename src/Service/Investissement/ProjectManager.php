<?php

namespace App\Service\Investissement;

use App\Entity\Investissement\Project;

class ProjectManager
{
    private const STATUTS_AUTORISES = [
        'pending',
        'in_progress',
        'funded',
        'completed',
    ];

    public function validate(Project $project): bool
    {
        $budget = $project->getRequiredBudget();
        if ($budget <= 0) {
            throw new \InvalidArgumentException('Le budget requis doit être positif');
        }

        $statut = $project->getStatus();
        if (!in_array($statut, self::STATUTS_AUTORISES, true)) {
            throw new \InvalidArgumentException('Statut de projet invalide');
        }

        return true;
    }
}