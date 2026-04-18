<?php

namespace App\Service\Investissement;

use App\Entity\Investissement\Negotiation;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Analyses negotiations using OpenAI GPT-4o-mini.
 * Falls back to rule-based local analysis when the API is unavailable.
 */
class AiNegotiationService
{
    private const MODEL   = 'gpt-4o-mini';
    private const API_URL = 'https://api.openai.com/v1/chat/completions';

    public function __construct(
        private HttpClientInterface $httpClient,
        private ?string $openaiApiKey,
    ) {}

    /**
     * Analyse une négociation et ses messages (vue investisseur ou startup).
     * Appelé par NegociationController::analyser()
     */
    public function analyse(Negotiation $negotiation, array $messages, string $userType = 'investor'): array
    {
        $project = $negotiation->getProject();

        $conversation = '';
        foreach (array_slice($messages, -10) as $msg) {
            $sender        = $msg->getUser()?->getFirstName() ?? 'Utilisateur';
            $conversation .= "[$sender] : " . $msg->getMessage() . "\n";
        }

        $roleLabel = $userType === 'startup' ? 'la startup' : "l'investisseur";

        $prompt = sprintf(
            "Tu es un expert en négociation d'investissement. Analyse cette négociation du point de vue de %s.\n"
            . "Projet : %s\nMontant proposé : %s TND\nStatut : %s\n\n"
            . "Historique récent :\n%s\n"
            . "Donne : sentiment général (positif/neutre/négatif), points clés, risques, recommandation (1-2 phrases). "
            . "Réponds en JSON : {\"sentiment\":\"...\",\"points_cles\":[...],\"risques\":[...],\"recommandation\":\"...\"}",
            $roleLabel,
            $project?->getTitle() ?? 'Inconnu',
            $negotiation->getProposed_amount() ?? 'N/D',
            $negotiation->getStatus() ?? 'N/D',
            $conversation ?: 'Aucun message encore.'
        );

        $result = $this->callApi($prompt);

        if ($result) {
            $clean  = preg_replace('/```json\s*|\s*```/', '', $result);
            $parsed = json_decode($clean, true);
            if (is_array($parsed)) {
                return array_merge($parsed, ['source' => 'ai']);
            }
            return ['raw' => $result, 'source' => 'ai'];
        }

        return $this->localAnalysis($negotiation, $userType);
    }

    /**
     * Génère un brouillon de message de négociation.
     * Appelé par NegociationController::draft()
     */
    public function generateDraft(Negotiation $negotiation, array $messages, string $style = 'professionnel'): string
    {
        $project = $negotiation->getProject();

        $styleDesc = match ($style) {
            'diplomatique' => 'diplomate et conciliant',
            'direct'       => 'direct et factuel',
            'persuasif'    => 'persuasif et convaincant',
            default        => 'professionnel et courtois',
        };

        $prompt = sprintf(
            "Tu es un expert en communication d'affaires. Rédige un message de négociation %s en français.\n"
            . "Contexte : projet '%s', montant proposé %s TND, statut '%s'.\n"
            . "Le message doit faire 2-3 phrases, être percutant et adapté au style demandé. "
            . "Réponds uniquement avec le texte du message, sans introduction.",
            $styleDesc,
            $project?->getTitle() ?? 'ce projet',
            $negotiation->getProposed_amount() ?? 'N/D',
            $negotiation->getStatus() ?? 'N/D'
        );

        $result = $this->callApi($prompt);

        if ($result) {
            return trim($result);
        }

        return $this->localDraft($negotiation, $style);
    }

    /**
     * Analyse du point de vue de l'investisseur (ancienne API, conservée).
     */
    public function analyseForInvestor(Negotiation $negotiation): array
    {
        return $this->analyse($negotiation, [], 'investor');
    }

    /**
     * Analyse du point de vue de la startup (ancienne API, conservée).
     */
    public function analyseForStartup(Negotiation $negotiation): array
    {
        return $this->analyse($negotiation, [], 'startup');
    }

    /**
     * Génère un brouillon de message (ancienne API, conservée).
     */
    public function generateDraftMessage(Negotiation $negotiation, string $senderType = 'investor'): string
    {
        return $this->generateDraft($negotiation, [], 'professionnel');
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function callApi(string $prompt): ?string
    {
        if (empty($this->openaiApiKey)) {
            return null;
        }

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openaiApiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'       => self::MODEL,
                    'messages'    => [['role' => 'user', 'content' => $prompt]],
                    'max_tokens'  => 400,
                    'temperature' => 0.5,
                ],
                'timeout' => 15,
            ]);

            $data    = $response->toArray(false);
            $content = $data['choices'][0]['message']['content'] ?? null;
            return $content ? trim($content) : null;

        } catch (\Throwable) {
            return null;
        }
    }

    private function localAnalysis(Negotiation $negotiation, string $userType): array
    {
        return [
            'sentiment'      => 'neutre',
            'points_cles'    => ['Négociation en cours', 'Montant à confirmer'],
            'risques'        => ['Analyse IA indisponible'],
            'recommandation' => 'Continuez la discussion pour affiner les termes.',
            'source'         => 'local',
        ];
    }

    private function localDraft(Negotiation $negotiation, string $style): string
    {
        $project = $negotiation->getProject();
        $title   = $project?->getTitle() ?? 'ce projet';
        $amount  = $negotiation->getProposed_amount() ?? 'N/D';

        return "Bonjour, suite à nos échanges concernant le projet « $title », "
            . "je souhaite confirmer mon intérêt pour un investissement de $amount TND. "
            . "Merci de me faire part de vos disponibilités pour avancer.";
    }
}
