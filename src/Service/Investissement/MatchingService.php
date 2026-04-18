<?php

namespace App\Service\Investissement;

use App\Entity\UsersAvis\User;
use App\Repository\InvestmentRepository;
use App\Repository\ProjectRepository;

class MatchingService
{
    public function __construct(
        private InvestmentRepository $investmentRepository,
        private ProjectRepository    $projectRepository,
    ) {}

    /**
     * Alias used by DashboardInvestisseurController.
     */
    public function matchProjects(User $investor, int $limit = 6): array
    {
        return $this->getMatchingProjectsForInvestor($investor, $limit);
    }

    /**
     * Retourne les projets ouverts aux investissements, triés par pertinence
     * par rapport au profil de l'investisseur (secteurs déjà investis en priorité).
     */
    public function getMatchingProjectsForInvestor(User $investor, int $limit = 6): array
    {
        $openProjects = $this->projectRepository->findEnCours(20);

        if (empty($openProjects)) {
            return [];
        }

        // Projets dans lesquels l'investisseur a déjà investi (pour éviter doublons inutiles)
        $alreadyInvested = $this->investmentRepository->findLastByUser($investor, 50);
        $investedProjectIds = array_map(
            fn($inv) => $inv->getProject()?->getProject_id(),
            $alreadyInvested
        );

        // Secteurs préférés de l'investisseur
        $preferredSectors = [];
        foreach ($alreadyInvested as $inv) {
            $secteur = $inv->getProject()?->getSecteur();
            if ($secteur) {
                $preferredSectors[$secteur] = ($preferredSectors[$secteur] ?? 0) + 1;
            }
        }
        arsort($preferredSectors);
        $topSectors = array_keys(array_slice($preferredSectors, 0, 3, true));

        // Scoring
        $scored = [];
        foreach ($openProjects as $project) {
            $score = 0;

            // Bonus si même secteur
            if (in_array($project->getSecteur(), $topSectors, true)) {
                $score += 10;
            }

            // Petit bonus si déjà investi (confiance dans le projet)
            if (in_array($project->getProject_id(), $investedProjectIds, true)) {
                $score += 3;
            }

            $scored[] = ['project' => $project, 'score' => $score];
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_column(array_slice($scored, 0, $limit), 'project');
    }
}
