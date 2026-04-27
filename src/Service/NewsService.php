<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fetches latest world news from GNews API.
 *
 * GNews free tier: 100 requests/day, up to 10 articles per call.
 * Results are cached for 30 minutes to stay within quota.
 *
 * Article shape:
 *   title       string
 *   description string|null
 *   url         string
 *   image       string|null
 *   source      string
 *   published_at \DateTimeImmutable
 */
class NewsService
{
    private const CACHE_TTL    = 1800;          // 30 minutes
    private const GNEWS_URL    = 'https://gnews.io/api/v4/top-headlines';
    private const MAX_ARTICLES = 6;

    private const CATEGORY_KEYS = [
        'business'   => 'biz_news_business_v1',
        'technology' => 'biz_news_technology_v1',
        'world'      => 'biz_news_world_v1',
    ];

    private const GNEWS_TOPICS = [
        'business'   => 'business',
        'technology' => 'technology',
        'world'      => 'world',
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface      $cache,
        private ?string             $gnewsApiKey,
    ) {}

    /**
     * Returns an array of up to MAX_ARTICLES normalized article arrays.
     * Never throws — returns an empty array on any failure.
     *
     * @param string $category  'business' | 'technology' | 'world'
     */
    public function getLatestWorldNews(string $category = 'business'): array
    {
        if (!array_key_exists($category, self::CATEGORY_KEYS)) {
            $category = 'business';
        }

        $cacheKey = self::CATEGORY_KEYS[$category];

        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($category): array {
                $item->expiresAfter(self::CACHE_TTL);
                return $this->fetchGNews($category);
            });
        } catch (\Throwable) {
            return [];
        }
    }

    // ── GNews provider ────────────────────────────────────────────────────────

    private function fetchGNews(string $category): array
    {
        if (empty($this->gnewsApiKey) || str_starts_with($this->gnewsApiKey, 'your_')) {
            return [];
        }

        try {
            $response = $this->httpClient->request('GET', self::GNEWS_URL, [
                'timeout' => 8,
                'query'   => [
                    'token'  => $this->gnewsApiKey,
                    'topic'  => self::GNEWS_TOPICS[$category] ?? 'business',
                    'lang'   => 'en',
                    'country'=> 'any',
                    'max'    => self::MAX_ARTICLES,
                    'sortby' => 'publishedAt',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $data     = $response->toArray(false);
            $articles = $data['articles'] ?? [];

            return array_map([$this, 'normalizeGNews'], array_slice($articles, 0, self::MAX_ARTICLES));

        } catch (\Throwable) {
            return [];
        }
    }

    private function normalizeGNews(array $raw): array
    {
        $published = null;
        try {
            if (!empty($raw['publishedAt'])) {
                $published = new \DateTimeImmutable($raw['publishedAt']);
            }
        } catch (\Throwable) {}

        return [
            'title'        => trim($raw['title']       ?? ''),
            'description'  => trim($raw['description'] ?? '') ?: null,
            'url'          => trim($raw['url']          ?? ''),
            'image'        => $this->sanitizeImageUrl($raw['image'] ?? null),
            'source'       => trim($raw['source']['name'] ?? 'Unknown'),
            'published_at' => $published,
        ];
    }

    private function sanitizeImageUrl(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }
        $url = trim($url);
        if (!preg_match('#^https?://#i', $url)) {
            return null;
        }
        return $url;
    }
}
