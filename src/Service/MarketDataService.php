<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fetches live crypto prices (CoinGecko) and exchange rates (open.er-api.com).
 * Results are cached for 5 minutes to avoid hammering free APIs.
 */
class MarketDataService
{
    private const CACHE_TTL   = 300; // 5 minutes
    private const CACHE_KEY   = 'biz_market_data_v2';

    private const CRYPTO_COINS = [
        'bitcoin'     => ['symbol' => 'BTC', 'name' => 'Bitcoin',  'color' => '#f7931a'],
        'ethereum'    => ['symbol' => 'ETH', 'name' => 'Ethereum', 'color' => '#627eea'],
        'tether'      => ['symbol' => 'USDT','name' => 'Tether',   'color' => '#26a17b'],
        'binancecoin' => ['symbol' => 'BNB', 'name' => 'BNB',      'color' => '#f3ba2f'],
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface      $cache,
    ) {}

    /**
     * Returns combined market data or graceful fallback.
     *
     * @return array{crypto: array|null, rates: array|null, updated_at: \DateTimeImmutable, available: bool}
     */
    public function getMarketData(): array
    {
        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter(self::CACHE_TTL);

            $crypto = $this->fetchCrypto();
            $rates  = $this->fetchExchangeRates();

            return [
                'crypto'     => $crypto,
                'rates'      => $rates,
                'updated_at' => new \DateTimeImmutable(),
                'available'  => $crypto !== null || $rates !== null,
            ];
        });
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function fetchCrypto(): ?array
    {
        try {
            $ids      = implode(',', array_keys(self::CRYPTO_COINS));
            $response = $this->httpClient->request('GET', 'https://api.coingecko.com/api/v3/simple/price', [
                'query'   => [
                    'ids'                => $ids,
                    'vs_currencies'      => 'usd,eur',
                    'include_24hr_change'=> 'true',
                ],
                'timeout' => 6,
            ]);

            $data   = $response->toArray();
            $result = [];

            foreach (self::CRYPTO_COINS as $id => $meta) {
                $row = $data[$id] ?? null;
                if ($row === null) {
                    continue;
                }
                $result[] = [
                    'id'         => $id,
                    'symbol'     => $meta['symbol'],
                    'name'       => $meta['name'],
                    'color'      => $meta['color'],
                    'price_usd'  => $row['usd']            ?? null,
                    'price_eur'  => $row['eur']            ?? null,
                    'change_24h' => round($row['usd_24h_change'] ?? 0, 2),
                ];
            }

            return $result ?: null;

        } catch (\Throwable) {
            return null;
        }
    }

    private function fetchExchangeRates(): ?array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://open.er-api.com/v6/latest/EUR', [
                'timeout' => 6,
            ]);

            $data = $response->toArray();

            if (($data['result'] ?? '') !== 'success') {
                return null;
            }

            $r = $data['rates'] ?? [];

            $eurToUsd = (float) ($r['USD'] ?? 1.08);
            $eurToTnd = (float) ($r['TND'] ?? 3.34);
            $usdToTnd = $eurToUsd > 0 ? $eurToTnd / $eurToUsd : 3.10;

            return [
                'EUR_USD' => round($eurToUsd, 4),
                'EUR_TND' => round($eurToTnd, 4),
                'USD_TND' => round($usdToTnd, 4),
            ];

        } catch (\Throwable) {
            return null;
        }
    }
}
