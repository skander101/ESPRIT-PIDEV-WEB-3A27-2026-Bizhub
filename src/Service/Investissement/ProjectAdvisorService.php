<?php

namespace App\Service\Investissement;

use App\Entity\Investissement\Project;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ProjectAdvisorService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ?string $openaiApiKey,
    ) {}

    /**
     * Alias used by FrontProjetController::coachAnalyze().
     */
    public function analyzeProject(Project $project): array
    {
        return $this->advise($project);
    }

    /**
     * Génère des conseils IA pour améliorer la présentation d'un projet.
     */
    public function advise(Project $project): array
    {
        if (empty($this->openaiApiKey)) {
            return $this->localAdvice($project);
        }

        $prompt = sprintf(
            "Tu es un expert en levée de fonds startups. "
            . "Analyse ce projet et donne 3 conseils pratiques pour améliorer son attractivité:\n"
            . "Titre: %s\nSecteur: %s\nBudget requis: %s TND\n"
            . "Description: %s\n\nRéponds en français avec une liste numérotée.",
            $project->getTitle(),
            $project->getSecteur() ?? 'non précisé',
            number_format((float) $project->getRequiredBudget(), 0, ',', ' '),
            substr($project->getDescription() ?? 'Aucune description', 0, 300)
        );

        try {
            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openaiApiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'      => 'gpt-4o-mini',
                    'messages'   => [['role' => 'user', 'content' => $prompt]],
                    'max_tokens' => 350,
                ],
                'timeout' => 12,
            ]);

            $data    = $response->toArray();
            $content = $data['choices'][0]['message']['content'] ?? null;

            return [
                'conseils' => $content,
                'source'   => 'ai',
            ];

        } catch (\Throwable) {
            return $this->localAdvice($project);
        }
    }

    /**
     * Score d'attractivité d'un projet (0-100).
     */
    public function scoreProject(Project $project): int
    {
        $score = 0;

        if ($project->getTitle() && strlen($project->getTitle()) > 10) {
            $score += 20;
        }
        if ($project->getDescription() && strlen($project->getDescription()) > 100) {
            $score += 25;
        }
        if ($project->getSecteur()) {
            $score += 15;
        }
        if ($project->getRequiredBudget() > 0) {
            $score += 20;
        }
        if ($project->getInvestments()->count() > 0) {
            $score += 20;
        }

        return min(100, $score);
    }

    private function localAdvice(Project $project): array
    {
        $tips = [];

        if (!$project->getDescription() || strlen($project->getDescription()) < 100) {
            $tips[] = 'Ajoutez une description détaillée (au moins 150 mots) pour rassurer les investisseurs.';
        }
        if (!$project->getSecteur()) {
            $tips[] = 'Précisez le secteur d\'activité pour cibler les bons investisseurs.';
        }
        $tips[] = 'Mettez à jour le statut du projet régulièrement pour montrer sa progression.';

        return [
            'conseils' => implode("\n", array_map(fn($i, $t) => ($i + 1) . '. ' . $t, array_keys($tips), $tips)),
            'source'   => 'local',
        ];
    }
}
