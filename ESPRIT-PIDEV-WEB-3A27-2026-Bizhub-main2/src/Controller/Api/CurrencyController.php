<?php

namespace App\Controller\Api;

use App\Service\MarketDataService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class CurrencyController extends AbstractController
{
    public function __construct(
        private MarketDataService $marketDataService,
    ) {}

    /**
     * GET /api/convert?tnd=1000
     * Returns the equivalent amount in EUR and USD.
     */
    #[Route('/convert', name: 'api_currency_convert', methods: ['GET'])]
    public function convert(Request $request): JsonResponse
    {
        $tnd = $request->query->get('tnd', '');

        if ($tnd === '' || !is_numeric($tnd) || (float) $tnd < 0) {
            return $this->json(['error' => 'Paramètre tnd invalide.'], 400);
        }

        $amount = (float) $tnd;

        $market = $this->marketDataService->getMarketData();
        $rates  = $market['rates'] ?? null;

        // Fallback rates (TND to EUR ≈ 0.299, TND to USD ≈ 0.323)
        if ($rates !== null && isset($rates['EUR_TND'], $rates['USD_TND'])) {
            $eurTnd = (float) $rates['EUR_TND'];
            $usdTnd = (float) $rates['USD_TND'];

            $eur = $eurTnd > 0 ? $amount / $eurTnd : $amount * 0.299;
            $usd = $usdTnd > 0 ? $amount / $usdTnd : $amount * 0.323;
        } else {
            // Static fallback if API is unavailable
            $eur = $amount * 0.299;
            $usd = $amount * 0.323;
        }

        return $this->json([
            'eur'       => round($eur, 2),
            'usd'       => round($usd, 2),
            'source'    => $rates !== null ? 'live' : 'fallback',
        ]);
    }
}
