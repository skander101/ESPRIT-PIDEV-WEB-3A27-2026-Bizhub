<?php

namespace App\Service\Chatbot;

use Doctrine\ORM\EntityManagerInterface;

class DBAgent
{
    private const MODEL = '@cf/meta/llama-3.1-8b-instruct';
    private const MAX_TOKENS = 800;
    private const HISTORY_WINDOW = 6;

    public function __construct(
        private readonly CloudflareClient $cloudflareClient,
        private readonly ConversationHistory $history,
        private readonly EntityManagerInterface $em,
    ) {}

    public function reply(string $message, string $sessionId): AgentResponse
    {
        $dataContext = $this->fetchSafeData($message);

        $systemPrompt = 'You are BizHub\'s Hive Keeper — you guard the colony\'s data and answer questions about startup projects and investments using only the data summaries provided. Never expose sensitive data (IDs, emails, addresses, phone numbers) — a good keeper protects every bee in the hive. Never invent data. If the answer isn\'t in the hive, say so clearly. Keep answers concise and direct.';
        $historyMessages = $this->history->getHistory('db', $sessionId);

        $userContent = trim($message);
        if ($dataContext !== '') {
            $userContent .= "\n\nDatabase context:\n" . $dataContext;
        }

        $messages = [...$historyMessages, ['role' => 'user', 'content' => $userContent]];

        $text = $this->cloudflareClient->complete(self::MODEL, $systemPrompt, $messages, self::MAX_TOKENS);

        $this->history->appendAndSave('db', $sessionId, 'user', trim($message), self::HISTORY_WINDOW * 2);
        $this->history->appendAndSave('db', $sessionId, 'assistant', $text, self::HISTORY_WINDOW * 2);

        return new AgentResponse($text, 'db');
    }

    private function fetchSafeData(string $message): string
    {
        $input = mb_strtolower(trim($message));
        $parts = [];

        // User-related keywords
        if ($this->matches($input, ['user', 'utilisateur', 'member', 'membre', 'account', 'compte', 'registered', 'inscrit'])) {
            $parts[] = $this->getUserCount();
        }

        // Project/formation/popular/ranking keywords
        if ($this->matches($input, [
            'project', 'startup', 'formation', 'formateur', 'popular', 'most popular', 'top', 'ranking',
            'startup project', 'project list', 'all project'])) {
            $parts[] = $this->getProjectSummaries();
        }

        if ($this->matches($input, ['invest', 'investment', 'funding', 'fund', 'capital', 'deal'])) {
            $parts[] = $this->getInvestmentSummaries();
        }

        if ($this->matches($input, ['count', 'how many', 'total', 'number of', 'nombre'])) {
            $parts[] = $this->getCounts();
        }

        if ($this->matches($input, ['sector', 'secteur', 'industry', 'field'])) {
            $parts[] = $this->getSectorBreakdown();
        }

        if ($this->matches($input, ['stage', 'status', 'statut', 'phase'])) {
            $parts[] = $this->getStageBreakdown();
        }

        return implode("\n\n", array_filter($parts));
    }

    private function getUserCount(): string
    {
        try {
            $count = (int) $this->em
                ->createQuery('SELECT COUNT(u) FROM App\\Entity\\UsersAvis\\User u')
                ->getSingleScalarResult();
            return sprintf('Total registered users: %d', $count);
        } catch (\Throwable $e) {
            return 'User count unavailable: ' . $e->getMessage();
        }
    }

    private function matches(string $input, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($input, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function getProjectSummaries(): string
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('p.title', 'p.secteur', 'p.status', 'p.required_budget', 'p.business_model', 'p.market_scope', 'p.project_stage', 'p.description')
            ->from('App\Entity\Investissement\Project', 'p')
            ->where('p.status = :status')
            ->setParameter('status', 'in_progress')
            ->setMaxResults(10);

        $rows = $qb->getQuery()->getArrayResult();

        if (empty($rows)) {
            return 'No active projects found.';
        }

        $lines = ['Active projects:'];
        foreach ($rows as $row) {
            $desc = isset($row['description']) ? mb_substr((string) $row['description'], 0, 150) : 'N/A';
            $lines[] = sprintf(
                '- %s | Sector: %s | Budget: %s TND | Stage: %s | Business Model: %s | Market: %s | Description: %s',
                $row['title'] ?? 'Untitled',
                $row['secteur'] ?? 'Unknown',
                $row['required_budget'] ?? 'N/A',
                $row['project_stage'] ?? 'Unknown',
                $row['business_model'] ?? 'Unknown',
                $row['market_scope'] ?? 'Unknown',
                $desc
            );
        }

        return implode("\n", $lines);
    }

    private function getInvestmentSummaries(): string
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('i.amount', 'i.statut', 'i.type_investissement', 'i.payment_mode', 'p.title as project_title')
            ->from('App\Entity\Investissement\Investment', 'i')
            ->leftJoin('i.project', 'p')
            ->setMaxResults(10);

        $rows = $qb->getQuery()->getArrayResult();

        if (empty($rows)) {
            return 'No investments found.';
        }

        $lines = ['Investments:'];
        foreach ($rows as $row) {
            $lines[] = sprintf(
                '- Project: %s | Amount: %s TND | Status: %s | Type: %s | Payment: %s',
                $row['project_title'] ?? 'Unknown',
                $row['amount'] ?? 'N/A',
                $row['statut'] ?? 'Unknown',
                $row['type_investissement'] ?? 'Unknown',
                $row['payment_mode'] ?? 'Unknown'
            );
        }

        return implode("\n", $lines);
    }

    private function getCounts(): string
    {
        $projectCount = (int) $this->em->createQuery('SELECT COUNT(p) FROM App\Entity\Investissement\Project p')->getSingleScalarResult();
        $investmentCount = (int) $this->em->createQuery('SELECT COUNT(i) FROM App\Entity\Investissement\Investment i')->getSingleScalarResult();
        $activeProjects = (int) $this->em->createQuery('SELECT COUNT(p) FROM App\Entity\Investissement\Project p WHERE p.status = :status')->setParameter('status', 'in_progress')->getSingleScalarResult();
        $fundedProjects = (int) $this->em->createQuery('SELECT COUNT(p) FROM App\Entity\Investissement\Project p WHERE p.status = :status')->setParameter('status', 'funded')->getSingleScalarResult();

        return sprintf(
            'Counts - Total projects: %d | Active projects: %d | Funded projects: %d | Total investments: %d',
            $projectCount,
            $activeProjects,
            $fundedProjects,
            $investmentCount
        );
    }

    private function getSectorBreakdown(): string
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('p.secteur', 'COUNT(p) as cnt')
            ->from('App\Entity\Investissement\Project', 'p')
            ->groupBy('p.secteur')
            ->orderBy('cnt', 'DESC');

        $rows = $qb->getQuery()->getArrayResult();

        if (empty($rows)) {
            return 'No sector data available.';
        }

        $lines = ['Projects by sector:'];
        foreach ($rows as $row) {
            $lines[] = sprintf('- %s: %d projects', $row['secteur'] ?? 'Unknown', (int) $row['cnt']);
        }

        return implode("\n", $lines);
    }

    private function getStageBreakdown(): string
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('p.project_stage', 'COUNT(p) as cnt')
            ->from('App\Entity\Investissement\Project', 'p')
            ->groupBy('p.project_stage')
            ->orderBy('cnt', 'DESC');

        $rows = $qb->getQuery()->getArrayResult();

        if (empty($rows)) {
            return 'No stage data available.';
        }

        $lines = ['Projects by stage:'];
        foreach ($rows as $row) {
            $lines[] = sprintf('- %s: %d projects', $row['project_stage'] ?? 'Unknown', (int) $row['cnt']);
        }

        return implode("\n", $lines);
    }
}
