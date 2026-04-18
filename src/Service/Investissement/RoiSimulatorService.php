<?php

namespace App\Service\Investissement;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class RoiSimulatorService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ?string $openaiApiKey,
    ) {}

    /**
     * Simule le ROI pour un investissement donné.
     *
     * @param float  $amount       Montant investi (TND)
     * @param string $type         Type d'investissement (prise_participation, pret_convertible, ...)
     * @param string $duree        Durée (3m, 6m, 12m, 24m, 36m, 60m)
     * @param string $secteur      Secteur du projet
     * @return array
     */
    public function simulate(float $amount, string $type, string $duree, string $secteur = ''): array
    {
        $localResult = $this->localSimulation($amount, $type, $duree);

        if (empty($this->openaiApiKey)) {
            return $localResult;
        }

        $mois = $this->dureeToMois($duree);

        $prompt = sprintf(
            "Simule le retour sur investissement pour:\n"
            . "- Montant: %s TND\n"
            . "- Type: %s\n"
            . "- Durée: %d mois\n"
            . "- Secteur: %s\n"
            . "Donne: ROI optimiste, réaliste, pessimiste (en %% et en TND), "
            . "et 2 facteurs de risque clés. Format JSON. En français.",
            number_format($amount, 0, ',', ' '),
            $type,
            $mois,
            $secteur ?: 'non précisé'
        );

        try {
            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openaiApiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'       => 'gpt-4o-mini',
                    'messages'    => [['role' => 'user', 'content' => $prompt]],
                    'max_tokens'  => 400,
                    'temperature' => 0.3,
                ],
                'timeout' => 12,
            ]);

            $data    = $response->toArray();
            $content = $data['choices'][0]['message']['content'] ?? null;

            if ($content) {
                // Tenter de décoder le JSON renvoyé par l'IA
                $clean = preg_replace('/```json\s*|\s*```/', '', $content);
                $parsed = json_decode($clean, true);
                if (is_array($parsed)) {
                    return array_merge($localResult, ['ai' => $parsed, 'source' => 'ai']);
                }
                return array_merge($localResult, ['ai_raw' => $content, 'source' => 'ai']);
            }

        } catch (\Throwable) {
            // fall through to local
        }

        return $localResult;
    }

    private function localSimulation(float $amount, string $type, string $duree): array
    {
        $mois    = $this->dureeToMois($duree);
        $annees  = $mois / 12;

        $rates = match ($type) {
            'prise_participation' => ['pessimiste' => -0.10, 'realiste' => 0.15, 'optimiste' => 0.35],
            'pret_convertible'    => ['pessimiste' => 0.05,  'realiste' => 0.12, 'optimiste' => 0.22],
            'pret_simple'         => ['pessimiste' => 0.04,  'realiste' => 0.08, 'optimiste' => 0.12],
            'don'                 => ['pessimiste' => 0.0,   'realiste' => 0.0,  'optimiste' => 0.0],
            default               => ['pessimiste' => 0.05,  'realiste' => 0.10, 'optimiste' => 0.20],
        };

        $result = [];
        foreach ($rates as $scenario => $rate) {
            $annualRate = pow(1 + $rate, $annees) - 1;
            $retour     = $amount * $annualRate;
            $result[$scenario] = [
                'taux'      => round($rate * 100 * $annees, 1) . '%',
                'retour'    => round($retour, 2),
                'total'     => round($amount + $retour, 2),
            ];
        }

        return array_merge($result, [
            'source'  => 'local',
            'montant' => $amount,
            'duree'   => $duree,
            'type'    => $type,
        ]);
    }

    private function dureeToMois(string $duree): int
    {
        return (int) rtrim($duree, 'm');
    }
}
