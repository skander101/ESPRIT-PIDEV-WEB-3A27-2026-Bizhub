<?php

namespace App\Service\Investissement;

use App\Entity\Investissement\Project;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Coach IA pour les startups.
 * Analyse un projet et retourne des recommandations concrètes.
 *
 * Structure retournée :
 *   analyse_globale      string  — paragraphe de synthèse
 *   points_forts         string[] — 3-4 points forts
 *   points_faibles       string[] — 3-4 points à améliorer
 *   suggestions          string[] — 4-5 suggestions concrètes
 *   scores               { qualite: int, clarte: int, attractivite: int }  (0-100)
 *   description_amelioree string — description réécrite
 *   source               "openai"|"local"
 *   analyzed_at          \DateTimeImmutable
 */
class ProjectAdvisorService
{
    private const OPENAI_URL = 'https://api.openai.com/v1/chat/completions';
    private const MODEL      = 'gpt-4o-mini';
    private const TIMEOUT    = 25;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string              $openaiApiKey,
    ) {}

    // ── Public ────────────────────────────────────────────────────────────────

    public function analyzeProject(Project $project): array
    {
        if (empty($this->openaiApiKey) || str_starts_with($this->openaiApiKey, 'your_')) {
            return $this->localAnalysis($project, 'Clé API non configurée');
        }

        try {
            $result                = $this->callOpenAi($project);
            $result['source']      = 'openai';
            $result['analyzed_at'] = new \DateTimeImmutable();
            return $result;
        } catch (\Throwable $e) {
            return $this->localAnalysis($project, $e->getMessage());
        }
    }

    // ── OpenAI ────────────────────────────────────────────────────────────────

    private function callOpenAi(Project $project): array
    {
        [$system, $user] = $this->buildPrompts($project);

        $response = $this->httpClient->request('POST', self::OPENAI_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => self::TIMEOUT,
            'json'    => [
                'model'       => self::MODEL,
                'temperature' => 0.4,
                'max_tokens'  => 1600,
                'messages'    => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user',   'content' => $user],
                ],
            ],
        ]);

        $code = $response->getStatusCode();
        if ($code === 429) throw new \RuntimeException('quota_exceeded');
        if ($code === 401 || $code === 403) throw new \RuntimeException('invalid_key');
        if ($code !== 200) throw new \RuntimeException('http_error_' . $code);

        $body    = $response->toArray(false);
        $content = trim($body['choices'][0]['message']['content'] ?? '');

        return $this->parseJson($content);
    }

    private function buildPrompts(Project $project): array
    {
        $secteurLabel  = array_search($project->getSecteur() ?? 'autre', Project::SECTEURS) ?: 'Autre';
        $statutLabel   = array_search($project->getStatus() ?? 'pending', Project::STATUTS) ?: 'Inconnu';
        $bmLabel       = $project->getBusinessModel()
            ? (array_search($project->getBusinessModel(), Project::BUSINESS_MODELS) ?: $project->getBusinessModel())
            : null;
        $marcheLabel   = $project->getMarketScope()
            ? (array_search($project->getMarketScope(), Project::MARCHES) ?: $project->getMarketScope())
            : null;
        $stadeLabel    = $project->getProjectStage()
            ? (array_search($project->getProjectStage(), Project::STADES) ?: $project->getProjectStage())
            : null;

        // Helper : format optional field or return fallback string
        $opt = static fn(?string $v, string $fallback = 'Non renseigné'): string => trim($v ?? '') ?: $fallback;

        $system = <<<PROMPT
Tu es un coach expert en entrepreneuriat et en levée de fonds pour startups tunisiennes.
Tu analyses des projets de startups et tu fournis des recommandations concrètes, bienveillantes mais directes.
Ton objectif est d'aider la startup à améliorer son projet pour attirer des investisseurs.
Réponds UNIQUEMENT en JSON valide, sans texte avant ni après.
PROMPT;

        $user = sprintf(
            <<<PROMPT
Analyse ce projet de startup et retourne un JSON avec exactement cette structure :

{
  "analyse_globale": "paragraphe de 3-4 phrases sur l'état général du projet",
  "points_forts": ["point fort 1", "point fort 2", "point fort 3"],
  "points_faibles": ["faiblesse 1", "faiblesse 2", "faiblesse 3"],
  "suggestions": ["suggestion concrète 1", "suggestion concrète 2", "suggestion concrète 3", "suggestion concrète 4"],
  "scores": {
    "qualite": 75,
    "clarte": 60,
    "attractivite": 70
  },
  "description_amelioree": "version améliorée et professionnelle de la description, en 2-3 paragraphes, orientée investisseurs"
}

Projet à analyser :
- Titre          : %s
- Secteur        : %s
- Statut         : %s
- Budget requis  : %s TND
- Stade          : %s
- Marché visé    : %s
- Modèle éco.    : %s

--- Description générale ---
%s

--- Problème résolu ---
%s

--- Solution proposée ---
%s

--- Public cible ---
%s

--- Avantage concurrentiel ---
%s

--- Utilisation du financement ---
%s

--- Prévisions financières ---
%s

--- Équipe & ressources ---
%s

Les scores sont sur 100. Sois précis et constructif. Tout en français.
PROMPT,
            $project->getTitle() ?? 'Sans titre',
            $secteurLabel,
            $statutLabel,
            number_format((float)($project->getRequiredBudget() ?? 0), 0, ',', ' '),
            $opt($stadeLabel),
            $opt($marcheLabel),
            $opt($bmLabel),
            $opt($project->getDescription(), 'Aucune description fournie.'),
            $opt($project->getProblemDescription()),
            $opt($project->getSolutionDescription()),
            $opt($project->getTargetAudience()),
            $opt($project->getCompetitiveAdvantage()),
            $opt($project->getFundingUsage()),
            $opt($project->getFinancialForecast()),
            $opt($project->getTeamDescription())
        );

        return [$system, $user];
    }

    private function parseJson(string $content): array
    {
        // Strip possible markdown code fences
        $content = preg_replace('/^```(?:json)?\s*/i', '', trim($content));
        $content = preg_replace('/\s*```$/', '', $content);

        $data = json_decode(trim($content), true);

        if (!is_array($data)) {
            throw new \RuntimeException('invalid_json');
        }

        return [
            'analyse_globale'       => (string)($data['analyse_globale'] ?? ''),
            'points_forts'          => (array)($data['points_forts'] ?? []),
            'points_faibles'        => (array)($data['points_faibles'] ?? []),
            'suggestions'           => (array)($data['suggestions'] ?? []),
            'scores'                => [
                'qualite'       => (int)($data['scores']['qualite'] ?? 50),
                'clarte'        => (int)($data['scores']['clarte'] ?? 50),
                'attractivite'  => (int)($data['scores']['attractivite'] ?? 50),
            ],
            'description_amelioree' => (string)($data['description_amelioree'] ?? ''),
        ];
    }

    // ── Fallback local ────────────────────────────────────────────────────────

    private function localAnalysis(Project $project, string $reason): array
    {
        $hasDesc    = !empty(trim($project->getDescription() ?? ''));
        $descLen    = strlen($project->getDescription() ?? '');
        $budget     = (float)($project->getRequiredBudget() ?? 0);
        $secteur    = $project->getSecteur() ?? 'autre';
        $title      = $project->getTitle() ?? 'Votre projet';

        // Count enriched fields filled in
        $enrichedFilled = 0;
        if (!empty(trim($project->getProblemDescription() ?? '')))    $enrichedFilled++;
        if (!empty(trim($project->getSolutionDescription() ?? '')))   $enrichedFilled++;
        if (!empty(trim($project->getTargetAudience() ?? '')))        $enrichedFilled++;
        if ($project->getBusinessModel())                              $enrichedFilled++;
        if ($project->getMarketScope())                                $enrichedFilled++;
        if (!empty(trim($project->getCompetitiveAdvantage() ?? '')))  $enrichedFilled++;
        if ($project->getProjectStage())                               $enrichedFilled++;
        if (!empty(trim($project->getFundingUsage() ?? '')))          $enrichedFilled++;
        if (!empty(trim($project->getFinancialForecast() ?? '')))     $enrichedFilled++;
        if (!empty(trim($project->getTeamDescription() ?? '')))       $enrichedFilled++;

        // Scores boosted by how many enriched fields are filled
        $enrichBonus  = (int)($enrichedFilled * 4);
        $qualite      = min(100, ($hasDesc ? min(70, 40 + (int)($descLen / 15)) : 25) + $enrichBonus);
        $clarte       = min(100, ($hasDesc ? min(65, 35 + (int)($descLen / 20)) : 20) + $enrichBonus);
        $attractivite = min(100, (in_array($secteur, ['tech', 'fintech', 'energie'], true) ? 60 : 45) + $enrichBonus);

        $pointsForts = ['Projet bien identifié avec un titre clair'];
        if ($hasDesc) $pointsForts[] = 'Description du projet présente';
        if ($budget > 0) $pointsForts[] = 'Budget requis défini (' . number_format($budget, 0, ',', ' ') . ' TND)';
        if (in_array($secteur, ['tech', 'fintech'], true)) $pointsForts[] = 'Secteur à fort potentiel de croissance';
        if ($enrichedFilled >= 5) $pointsForts[] = 'Dossier bien enrichi (' . $enrichedFilled . '/10 champs métier renseignés)';
        if ($project->getProjectStage()) $pointsForts[] = 'Stade d\'avancement précisé : ' . ($project->getProjectStage());
        if ($project->getBusinessModel()) $pointsForts[] = 'Modèle économique défini';

        $pointsFaibles = [];
        if (!$hasDesc) $pointsFaibles[] = 'Description du projet manquante ou très courte';
        elseif ($descLen < 200) $pointsFaibles[] = 'Description trop brève pour convaincre des investisseurs';
        if (empty(trim($project->getProblemDescription() ?? ''))) $pointsFaibles[] = 'Problème résolu non renseigné — essentiel pour les investisseurs';
        if (empty(trim($project->getSolutionDescription() ?? ''))) $pointsFaibles[] = 'Solution proposée non décrite';
        if (!$project->getBusinessModel()) $pointsFaibles[] = 'Modèle économique non sélectionné';
        if (empty(trim($project->getCompetitiveAdvantage() ?? ''))) $pointsFaibles[] = 'Avantage concurrentiel non renseigné';
        if (empty(trim($project->getFundingUsage() ?? ''))) $pointsFaibles[] = 'Utilisation du financement non précisée';

        // Keep at most 4 weaknesses
        $pointsFaibles = array_slice($pointsFaibles, 0, 4);

        $suggestions = [];
        if (!$hasDesc || $descLen < 200) {
            $suggestions[] = 'Rédigez une description détaillée (min. 300 mots) expliquant le problème résolu, la solution et le marché cible.';
        }
        if (empty(trim($project->getProblemDescription() ?? ''))) {
            $suggestions[] = 'Renseignez le champ "Problème résolu" : expliquez précisément la douleur que vous adressez.';
        }
        if (empty(trim($project->getCompetitiveAdvantage() ?? ''))) {
            $suggestions[] = 'Décrivez votre avantage concurrentiel et ce qui vous différencie des solutions existantes.';
        }
        if (empty(trim($project->getFundingUsage() ?? ''))) {
            $suggestions[] = 'Expliquez comment le budget de ' . number_format($budget, 0, ',', ' ') . ' TND sera utilisé (répartition des dépenses).';
        }
        $suggestions[] = 'Ajoutez des données chiffrées : taille du marché, projections de revenus, nombre de clients potentiels.';
        if (empty(trim($project->getTeamDescription() ?? ''))) {
            $suggestions[] = 'Mentionnez l\'équipe fondatrice et son expertise pour rassurer les investisseurs.';
        }

        $suggestions = array_slice($suggestions, 0, 5);

        $descAmelioree = $hasDesc
            ? $project->getDescription()
            : sprintf(
                '%s est un projet innovant dans le secteur %s. Notre solution répond à un besoin réel du marché tunisien en offrant [décrire la valeur ajoutée]. Avec un budget de %s TND, nous prévoyons de [décrire les étapes de développement]. Notre équipe possède [décrire expertise] et nous visons [décrire objectifs à 12 mois].',
                $title,
                $secteur,
                number_format($budget, 0, ',', ' ')
            );

        return [
            'analyse_globale'       => sprintf(
                'Le projet « %s » dans le secteur %s présente un potentiel à développer (%d/10 champs métier renseignés). %s La prochaine étape prioritaire est d\'enrichir la présentation du projet pour maximiser son attractivité auprès des investisseurs.',
                $title,
                $secteur,
                $enrichedFilled,
                $hasDesc ? 'Une description est fournie mais elle mérite d\'être enrichie avec des données concrètes.' : 'La description du projet est insuffisante, ce qui limite fortement l\'attractivité auprès des investisseurs.'
            ),
            'points_forts'          => array_slice($pointsForts, 0, 4),
            'points_faibles'        => $pointsFaibles,
            'suggestions'           => $suggestions,
            'scores'                => [
                'qualite'       => $qualite,
                'clarte'        => $clarte,
                'attractivite'  => $attractivite,
            ],
            'description_amelioree' => $descAmelioree,
            'source'                => 'local',
            'analyzed_at'           => new \DateTimeImmutable(),
        ];
    }
}
