<?php

namespace App\Service\Marketplace;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service d'intégration avec l'API Groq (LLM rapide, compatible OpenAI).
 *
 * Deux fonctionnalités :
 *   1. generateOrderRecommendation() — recommandation de confirmation de commande
 *   2. generateMarketAnalysis()      — analyse de marché pour un produit
 *
 * Fallback transparent si la clé API manque ou si le service est indisponible.
 */
class GrokService
{
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const TIMEOUT = 12; // secondes

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string             $apiKey,
        private readonly string             $model,
        private readonly LoggerInterface    $logger,
    ) {}

    // ════════════════════════════════════════════════════════════════════
    //  RECOMMANDATION DE COMMANDE
    // ════════════════════════════════════════════════════════════════════

    /**
     * Génère une recommandation IA pour la confirmation d'une commande.
     *
     * @return array{text: string, priority: string, source: string}
     */
    public function generateOrderRecommendation(
        int    $commandeId,
        float  $montant,
        int    $score,
        string $decision,
        int    $nbHistorique
    ): array {
        $decisionLabel = match ($decision) {
            'auto_confirm' => 'confirmation automatique recommandée',
            'auto_reject'  => 'rejet automatique recommandé',
            default        => 'validation manuelle recommandée',
        };

        $prompt = <<<PROMPT
Tu analyses une commande B2B sur une plateforme marketplace de mise en relation startups/investisseurs en Tunisie.

Données de la commande :
- Identifiant : #{$commandeId}
- Montant TTC : {$montant} TND
- Score IA algorithmique : {$score}/100
- Décision algorithmique : {$decisionLabel}
- Historique client : {$nbHistorique} commandes antérieures confirmées/payées/livrées

Génère une recommandation professionnelle et concise (3 phrases maximum) pour l'investisseur.
Inclus : niveau de risque (Faible / Modéré / Élevé) et une justification basée sur les données.
Sois direct, professionnel, et utile. Ne répète pas les chiffres bruts, explique-les.
PROMPT;

        $text = $this->call($prompt, 250);

        if ($text === null) {
            return $this->fallbackOrderRecommendation($score, $decision);
        }

        return [
            'text'     => trim($text),
            'priority' => $score >= 70 ? 'Élevé' : ($score >= 40 ? 'Modéré' : 'Faible'),
            'source'   => 'groq',
        ];
    }

    // ════════════════════════════════════════════════════════════════════
    //  ANALYSE DE MARCHÉ
    // ════════════════════════════════════════════════════════════════════

    /**
     * Génère une analyse de marché pour un produit/service.
     *
     * @return array{demand: string, competition: string, potential: string, summary: string, tips: string[], source: string}
     */
    public function generateMarketAnalysis(
        string  $productName,
        ?string $category,
        float   $price
    ): array {
        $categoryLabel = $category ?? 'Non catégorisé';

        $prompt = <<<PROMPT
Tu es un expert en analyse de marché B2B pour startups et investisseurs en Tunisie et au Maghreb.

Produit/Service à analyser :
- Nom : "{$productName}"
- Catégorie : {$categoryLabel}
- Prix unitaire : {$price} TND

Génère une analyse de marché structurée. Réponds UNIQUEMENT avec un objet JSON valide, sans markdown ni commentaires :
{
  "demand": "faible" | "moyenne" | "forte",
  "competition": "faible" | "moyenne" | "élevée",
  "potential": "risqué" | "prometteur" | "élevé",
  "summary": "Résumé stratégique en 2-3 phrases maximum",
  "tips": ["conseil pratique 1", "conseil pratique 2", "conseil pratique 3"]
}
PROMPT;

        $text = $this->call($prompt, 400);

        if ($text === null) {
            return $this->fallbackMarketAnalysis($productName);
        }

        try {
            // Extraction du JSON même s'il est entouré de texte
            if (preg_match('/\{[\s\S]*\}/u', $text, $matches)) {
                $data = json_decode($matches[0], true, 512, JSON_THROW_ON_ERROR);

                // Validation minimale des clés attendues
                $requiredKeys = ['demand', 'competition', 'potential', 'summary', 'tips'];
                foreach ($requiredKeys as $key) {
                    if (!isset($data[$key])) {
                        throw new \UnexpectedValueException("Missing key: {$key}");
                    }
                }

                return array_merge($data, ['source' => 'groq']);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('GrokService: JSON parse failed', [
                'response' => substr($text, 0, 200),
                'error'    => $e->getMessage(),
            ]);
        }

        return $this->fallbackMarketAnalysis($productName);
    }

    // ════════════════════════════════════════════════════════════════════
    //  PRIVATE HELPERS
    // ════════════════════════════════════════════════════════════════════

    private function call(string $prompt, int $maxTokens): ?string
    {
        // Ne pas appeler l'API si la clé n'est pas configurée
        if (empty($this->apiKey) || str_starts_with($this->apiKey, 'your_')) {
            $this->logger->info('GrokService: API key not configured — using fallback');
            return null;
        }

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'timeout' => self::TIMEOUT,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'       => $this->model,
                    'messages'    => [
                        [
                            'role'    => 'system',
                            'content' => 'Tu es un assistant expert en commerce B2B et analyse de marché. '
                                       . 'Réponds toujours en français, de manière concise et professionnelle. '
                                       . 'Ne dépasse jamais les limites de tokens demandées.',
                        ],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens'  => $maxTokens,
                    'temperature' => 0.35,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                $this->logger->warning('GrokService: API returned HTTP ' . $statusCode);
                return null;
            }

            $data = $response->toArray(false);
            return $data['choices'][0]['message']['content'] ?? null;

        } catch (\Throwable $e) {
            $this->logger->warning('GrokService: API call failed', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);
            return null;
        }
    }

    // ── Fallbacks statiques ──────────────────────────────────────────────

    private function fallbackOrderRecommendation(int $score, string $decision): array
    {
        $texts = [
            'auto_confirm' => 'Le profil client présente tous les indicateurs positifs requis. '
                . 'Le score algorithmique est élevé et l\'historique de commandes est favorable. '
                . 'Niveau de risque : Faible — la confirmation est recommandée.',

            'auto_reject'  => 'Le score algorithmique de cette commande est insuffisant. '
                . 'Le profil client présente des lacunes importantes (historique faible ou profil incomplet). '
                . 'Niveau de risque : Élevé — une analyse approfondie est nécessaire.',

            'manual'       => 'Cette commande présente un profil intermédiaire nécessitant une validation manuelle. '
                . 'Vérifiez l\'historique du client et la cohérence du montant. '
                . 'Niveau de risque : Modéré — décision à votre appréciation.',
        ];

        return [
            'text'     => $texts[$decision] ?? $texts['manual'],
            'priority' => $score >= 70 ? 'Élevé' : ($score >= 40 ? 'Modéré' : 'Faible'),
            'source'   => 'fallback',
        ];
    }

    private function fallbackMarketAnalysis(string $productName): array
    {
        return [
            'demand'      => 'moyenne',
            'competition' => 'moyenne',
            'potential'   => 'prometteur',
            'summary'     => "Le produit \"{$productName}\" s'inscrit dans un marché B2B en développement au Maghreb. "
                           . "Une étude des tendances sectorielles locales est recommandée pour affiner le positionnement "
                           . "et identifier les niches à fort potentiel.",
            'tips'        => [
                'Analysez la demande locale avant de fixer les prix finaux',
                'Différenciez-vous par la qualité de service et le suivi client',
                'Constituez des partenariats stratégiques durables avec des acteurs établis',
            ],
            'source'      => 'fallback',
        ];
    }
}
