<?php

namespace App\Service\Chatbot;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class NavAgent
{
    private const MODEL = '@cf/meta/llama-3.1-8b-instruct';
    private const MAX_TOKENS = 600;
    private const HISTORY_WINDOW = 4;

    private readonly string $routeList;

    public function __construct(
        private readonly CloudflareClient $cloudflareClient,
        private readonly ConversationHistory $history,
        private readonly RouterInterface $router,
        private readonly KernelInterface $kernel,
        private readonly RequestStack $requestStack,
    ) {
        $this->routeList = $this->buildRouteList();
    }

    public function reply(string $message, string $sessionId): AgentResponse
    {
        $currentRequest = $this->requestStack->getCurrentRequest();
        if ($currentRequest !== null) {
            $this->router->getContext()->fromRequest($currentRequest);
        }
        file_put_contents(dirname(__DIR__,3).'/var/log/nav_debug.log', date('H:i:s').' | ROUTES: ' . "\n" . $this->routeList . "\n", FILE_APPEND);
        $systemPrompt = sprintf(
            "You are the Scout Bee of BizHub — your job is to find the right cell in the hive.\n\n" .
            "AVAILABLE PAGES (format is 'route_name: Description'):\n%s\n\n" .
            "When the user wants to navigate, respond with ONLY this exact format:\n" .
            "On my way — I found it in the hive.\n" .
            "[NAV_LINK]{\"label\":\"Description\",\"route\":\"route_name\"}[/NAV_LINK]\n\n" .
            "RULES:\n" .
            "- Copy the route_name EXACTLY as it appears before the colon\n" .
            "- Use the Description as the label\n" .
            "- NEVER invent or modify route names\n" .
            "- NEVER use file paths as route names\n" .
            "- NEVER add extra text outside the format above\n" .
            "- If nothing matches: reply only with: This cell doesn't exist in the hive.",
            $this->routeList
        );  

        $historyMessages = $this->history->getHistory('nav', $sessionId);
        $messages = [...$historyMessages, ['role' => 'user', 'content' => trim($message)]];

        $rawText = $this->cloudflareClient->complete(self::MODEL, $systemPrompt, $messages, self::MAX_TOKENS);
        file_put_contents(dirname(__DIR__, 3) . '/var/log/nav_debug.log', date('H:i:s') . ' | RAW: [' . $rawText . ']' . "\n", FILE_APPEND);

        $navLinks = $this->parseNavLinks($rawText);
        $cleanText = $this->stripNavLinkBlocks($rawText);

        $this->history->appendAndSave('nav', $sessionId, 'user', trim($message), self::HISTORY_WINDOW * 2);
        $this->history->appendAndSave('nav', $sessionId, 'assistant', $rawText, self::HISTORY_WINDOW * 2);

        return new AgentResponse($cleanText, 'nav', $navLinks);
    }

    private function buildRouteList(): string
    {
        $routes = $this->router->getRouteCollection()->all();
        $lines = [];
        $filters = [
            'admin', 'back_', 'api_', 'webhook', 'csv', 'pdf', 'svg', 'badge', 'sidebar', 'track', 'notify',
            'mark_read', 'toggle', 'delete', 'remove', 'vider', 'annuler', 'refuser', 'confirmer', 'livrer',
            'approve', 'reject', 'reorder'
        ];
        $currentRequest = $this->requestStack->getCurrentRequest();
        if ($currentRequest !== null) {
            $this->router->getContext()->fromRequest($currentRequest);
        }
        foreach ($routes as $name => $route) {
            if (str_starts_with($name, '_')) continue;
            $skip = false;
            foreach ($filters as $f) {
                if (str_contains($name, $f)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;
            $methods = $route->getMethods();
            if ($methods && !in_array('GET', $methods, true)) continue;
            $path = $route->getPath();
            if (preg_match('/\{[^}?]+\}/', $path)) continue;
            try {
                $this->router->generate($name);
            } catch (\Throwable) {
                continue;
            }
            $lines[] = sprintf('%s: %s', $name, $this->humanize($name));
        }
        sort($lines, SORT_NATURAL | SORT_FLAG_CASE);
        return implode("\n", $lines);
    }

    private function humanize(string $routeName): string
    {
        // Remove common prefixes
        $name = preg_replace('/^(app_front_|app_|front_)/', '', $routeName);
        // Replace underscores with spaces
        $name = str_replace('_', ' ', $name);
        // Capitalize first letter of each word
        $name = ucwords($name);
        // Fix common abbreviations
        $name = str_replace([
            'Produit', 'Panier', 'Commande', 'Investissement',
            'Projet', 'Formation', 'Fournisseur', 'Formateur',
            'Investisseur', 'Startup', 'Index', 'New', 'Show',
            'Edit', 'Mes ', 'Liste'
        ], [
            'Product', 'Cart', 'Order', 'Investment',
            'Project', 'Training', 'Supplier', 'Trainer',
            'Investor', 'Startup', 'List', 'Create New', 'View',
            'Edit', 'My ', 'List'
        ], $name);
        return trim($name);
    }

    /**
     * @return array<int, array{label: string, url: string}>
     */
    private function parseNavLinks(string $text): array
    {
        $links = [];

        if (!preg_match_all('/\[NAV_LINK\](.*?)\[\/NAV_LINK\]/s', $text, $matches)) {
            return $links;
        }

        foreach ($matches[1] as $json) {
            $data = json_decode(trim($json), true);
            if (!is_array($data) || !isset($data['label'], $data['route'])) {
                continue;
            }

            try {
                $url = $this->router->generate($data['route']);
                $links[] = [
                    'label' => (string) $data['label'],
                    'url' => $url,
                ];
            } catch (\Throwable $e) {
                file_put_contents(
                    dirname(__DIR__, 3) . '/var/log/nav_debug.log',
                    date('H:i:s') . ' | ROUTE ERROR: ' . $e->getMessage() .
                    ' | route: ' . ($data['route'] ?? 'unknown') . "\n",
                    FILE_APPEND
                );
            }
        }

        return $links;
    }

    private function stripNavLinkBlocks(string $text): string
    {
        $text = preg_replace('/\[NAV_LINK\].*?\[\/NAV_LINK\]\s*/s', '', $text);
        return trim($text);
    }
}
