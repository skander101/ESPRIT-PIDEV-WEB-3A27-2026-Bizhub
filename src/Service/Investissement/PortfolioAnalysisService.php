<?php

namespace App\Service\Investissement;

use App\Entity\UsersAvis\User;
use App\Repository\InvestmentRepository;
use App\Repository\NegotiationRepository;
use App\Repository\Investissement\DealRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PortfolioAnalysisService
{
    public function __construct(
        private InvestmentRepository  $investmentRepository,
        private NegotiationRepository $negotiationRepository,
        private DealRepository        $dealRepository,
        private HttpClientInterface   $httpClient,
        private ?string $openaiApiKey,
    ) {}

    /**
     * Analyse complète du portefeuille d'un investisseur.
     */
    public function analyse(User $user): array
    {
        $userId     = $user->getUserId();
        $total      = $this->investmentRepository->getTotalInvestedByUser($user);
        $nb         = $this->investmentRepository->countByUser($user);
        $parStatut  = $this->investmentRepository->countByStatutForUser($user);
        $derniers   = $this->investmentRepository->findLastByUser($user, 10);
        $deals      = $this->dealRepository->findByBuyerId($userId);
        $nbDeals    = $this->dealRepository->countActiveByBuyerId($userId);

        // Répartition par type d'investissement
        $parType = [];
        foreach ($derniers as $inv) {
            $type = $inv->getTypeInvestissement() ?? 'non_specifie';
            $parType[$type] = ($parType[$type] ?? 0) + 1;
        }

        $aiInsight = $this->getAiInsight($user, $total, $nb, $parStatut);

        return [
            'total_investi'  => $total,
            'nb_placements'  => $nb,
            'par_statut'     => $parStatut,
            'par_type'       => $parType,
            'deals_actifs'   => $nbDeals,
            'derniers'       => $derniers,
            'ai_insight'     => $aiInsight,
        ];
    }

    /**
     * Alias for controllers that call analyzePortfolio().
     */
    public function analyzePortfolio(User $user): array
    {
        return $this->analyse($user);
    }

    private function getAiInsight(User $user, float $total, int $nb, array $parStatut): ?string
    {
        if (empty($this->openaiApiKey) || $nb === 0) {
            return null;
        }

        $statutsStr = implode(', ', array_map(
            fn($s, $c) => "$c $s",
            array_keys($parStatut),
            array_values($parStatut)
        ));

        $prompt = "Analyse ce portefeuille d'investissements sur BizHub:\n"
            . "- Total investi: " . number_format($total, 0, ',', ' ') . " TND\n"
            . "- Nombre de placements: $nb\n"
            . "- Répartition par statut: $statutsStr\n"
            . "Donne une analyse de risque courte (3 phrases max) et une recommandation clé. En français.";

        try {
            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openaiApiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'      => 'gpt-4o-mini',
                    'messages'   => [['role' => 'user', 'content' => $prompt]],
                    'max_tokens' => 200,
                ],
                'timeout' => 12,
            ]);

            $data = $response->toArray();
            return $data['choices'][0]['message']['content'] ?? null;

        } catch (\Throwable) {
            return null;
        }
    }
}
