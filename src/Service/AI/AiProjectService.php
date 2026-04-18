<?php

namespace App\Service\AI;

use App\Entity\Investissement\Project;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Améliore la description d'un projet startup via OpenAI (gpt-4o-mini).
 * Retourne toujours une chaîne non vide — en cas d'échec API, applique
 * une amélioration locale minimale (capitalisation + ponctuation).
 */
class AiProjectService
{
    private const MODEL      = 'gpt-4o-mini';
    private const MAX_TOKENS = 600;
    private const TIMEOUT    = 20;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string              $openaiApiKey,
    ) {}

    /**
     * Reformule et améliore la description en français de manière professionnelle.
     *
     * @throws \InvalidArgumentException si la description est vide
     */
    public function improveDescription(string $description, ?Project $project = null): string
    {
        $description = trim($description);

        if ($description === '') {
            throw new \InvalidArgumentException('La description ne peut pas être vide.');
        }

        if (empty($this->openaiApiKey)) {
            return $this->localImprove($description);
        }

        try {
            $userContent = $this->buildUserMessage($description, $project);

            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openaiApiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'    => self::MODEL,
                    'messages' => [
                        [
                            'role'    => 'system',
                            'content' => "Tu es un expert en rédaction professionnelle pour des projets d'investissement et de startups. "
                                . "Reformule la description du projet fournie par l'utilisateur en français : "
                                . "rends-la plus professionnelle, plus fluide, mieux structurée et plus convaincante pour des investisseurs, "
                                . "tout en conservant fidèlement le sens original et en intégrant naturellement les éléments de contexte fournis. "
                                . "Réponds UNIQUEMENT avec le texte amélioré, sans introduction, sans titre, sans guillemets, sans commentaire. "
                                . "Le texte doit faire entre 100 et 900 caractères.",
                        ],
                        [
                            'role'    => 'user',
                            'content' => $userContent,
                        ],
                    ],
                    'max_tokens'  => self::MAX_TOKENS,
                    'temperature' => 0.6,
                ],
                'timeout' => self::TIMEOUT,
            ]);

            $status = $response->getStatusCode();

            if (in_array($status, [429, 401], true)) {
                return $this->localImprove($description);
            }

            if ($status !== 200) {
                return $this->localImprove($description);
            }

            $data    = $response->toArray(false);
            $content = $data['choices'][0]['message']['content'] ?? null;

            if (!$content || trim($content) === '') {
                return $this->localImprove($description);
            }

            return trim($content);

        } catch (\Throwable) {
            return $this->localImprove($description);
        }
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function buildUserMessage(string $description, ?Project $project): string
    {
        $lines   = ["Améliore cette description de projet startup :\n\n" . $description];
        $context = [];

        if ($project !== null) {
            $opt = static fn(?string $v): string => trim($v ?? '');

            if ($opt($project->getProblemDescription())) {
                $context[] = 'Problème résolu : ' . $opt($project->getProblemDescription());
            }
            if ($opt($project->getSolutionDescription())) {
                $context[] = 'Solution : ' . $opt($project->getSolutionDescription());
            }
            if ($opt($project->getTargetAudience())) {
                $context[] = 'Public cible : ' . $opt($project->getTargetAudience());
            }
            if ($project->getMarketScope()) {
                $label     = array_search($project->getMarketScope(), Project::MARCHES) ?: $project->getMarketScope();
                $context[] = 'Marché visé : ' . $label;
            }
            if ($project->getBusinessModel()) {
                $label     = array_search($project->getBusinessModel(), Project::BUSINESS_MODELS) ?: $project->getBusinessModel();
                $context[] = 'Modèle économique : ' . $label;
            }
            if ($opt($project->getCompetitiveAdvantage())) {
                $context[] = 'Avantage concurrentiel : ' . $opt($project->getCompetitiveAdvantage());
            }
        }

        if (!empty($context)) {
            $lines[] = "\nContexte du projet (à intégrer dans la reformulation) :\n" . implode("\n", $context);
        }

        return implode('', $lines);
    }

    private function localImprove(string $text): string
    {
        $text = ucfirst($text);

        if (!in_array(mb_substr($text, -1), ['.', '!', '?'], true)) {
            $text .= '.';
        }

        return $text;
    }
}
