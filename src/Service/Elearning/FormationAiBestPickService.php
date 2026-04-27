<?php

declare(strict_types=1);

namespace App\Service\Elearning;

use App\Entity\Elearning\Formation;
use App\Entity\Elearning\Participation;
use App\Entity\UsersAvis\User;
use App\Repository\Elearning\FormationRepository;
use App\Repository\Elearning\ParticipationRepository;
use App\Service\Marketplace\GrokService;

/**
 * Suggère une formation pertinente via Groq à partir de l'historique d'inscriptions.
 */
final class FormationAiBestPickService
{
    private const SYSTEM_PROMPT_BASE = <<<'SYS'
Tu es conseiller pédagogique sur BizHub (Tunisie). On te donne l'historique d'inscriptions d'un apprenant
et une liste de formations candidates (chacune avec formation_id, titre, modalité, extrait de description).
Si l'apprenant a indiqué des objectifs ou préférences, ils ont la PRIORITÉ ABSOLUE pour le choix :
tu dois choisir la formation dont le titre ou le résumé correspond le mieux à ce texte, en le reliant
de façon explicite à son historique (thèmes déjà suivis, modalité habituelle) quand c'est pertinent.
Choisis UNE formation la plus pertinente (thèmes passés, modalité, préférences textuelles).
Réponds UNIQUEMENT avec un objet JSON valide (sans markdown, sans texte autour) :
{"formation_id": <entier>, "title": "<titre exact parmi les candidats>", "reason": "<2 à 4 phrases en français>"}
Le formation_id et le title doivent correspondre exactement à une entrée de la liste des candidats.
SYS;

    private const SYSTEM_PROMPT_FULL_CATALOG = <<<'SYS'
Même règles que d'habitude, mais ici l'apprenant a déjà un dossier actif sur toutes les autres sessions,
ou le catalogue ne propose que des formations où il apparaît déjà : la liste peut inclure des formations
qu'il a déjà suivies ou annulées. Choisis quand même la formation la plus utile pour ses objectifs actuels
(approfondissement, remise à niveau, complément) en t'appuyant sur le champ deja_inscription et sur l'historique.
Réponds UNIQUEMENT avec le même JSON : formation_id, title, reason.
SYS;

    public function __construct(
        private readonly GrokService $grokService,
        private readonly ParticipationRepository $participationRepository,
        private readonly FormationRepository $formationRepository,
        private readonly FormationRecommendationService $formationRecommendationService,
    ) {
    }

    /**
     * @return array{
     *   ok: bool,
     *   message?: string,
     *   formation_id?: int,
     *   title?: string,
     *   reason?: string,
     *   en_ligne?: bool,
     *   source?: string
     * }
     */
    public function suggestForUser(User $user, string $notes = ''): array
    {
        if ($user->getUserType() === 'formateur') {
            return [
                'ok'      => false,
                'message' => 'Cette suggestion est réservée aux participants (comptes non formateur).',
            ];
        }

        $notes = trim($notes);
        if (function_exists('mb_substr')) {
            $notes = mb_substr($notes, 0, 500, 'UTF-8');
        } elseif (strlen($notes) > 500) {
            $notes = substr($notes, 0, 500);
        }

        $anyPastFormationIds = $this->participationRepository->findEngagedFormationIdsByUser($user);
        $activeEnrollmentIds = $this->participationRepository->findActiveEnrollmentFormationIdsByUser($user);
        /** @var list<Participation> $participations */
        $participations = $this->participationRepository->findRecentParticipationsWithFormation($user, 22);

        $all = $this->formationRepository->findAllOrderedByStartDate();
        if ($all === []) {
            return [
                'ok'      => false,
                'message' => 'Aucune formation n’est publiée pour le moment.',
            ];
        }

        $candidates = $this->sliceCandidatesExcludingIds($all, $activeEnrollmentIds, 36);
        $fullCatalogMode = false;
        if ($candidates === []) {
            $fullCatalogMode = true;
            $candidates = $this->sliceCandidatesExcludingIds($all, [], 40);
        }

        $historyPayload = $this->buildHistoryPayload($participations);
        $candidatesPayload = $this->buildCandidatesPayload(
            $candidates,
            $fullCatalogMode ? $anyPastFormationIds : null
        );

        $userPrompt = "Historique d'inscriptions (du plus récent au plus ancien) :\n"
            . json_encode($historyPayload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            . "\n\nFormations candidates :\n"
            . json_encode($candidatesPayload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if ($notes !== '') {
            $userPrompt .= "\n\nObjectifs / préférences de l'apprenant (à respecter en priorité pour le choix) :\n« "
                . $notes
                . " »";
        }

        $system = self::SYSTEM_PROMPT_BASE;
        if ($fullCatalogMode) {
            $system .= "\n\n" . self::SYSTEM_PROMPT_FULL_CATALOG;
        }

        $raw = $this->grokService->chatWithMessages([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $userPrompt],
        ], 520, 0.28);

        $parsed = $this->parseFormationJson($raw);
        if (is_array($parsed)) {
            $picked = $this->resolveCandidate($candidates, $parsed);
            if ($picked instanceof Formation) {
                $reason = trim((string) ($parsed['reason'] ?? ''));
                if ($reason === '') {
                    $reason = 'Cette formation correspond le mieux à votre parcours et aux sessions encore ouvertes.';
                }

                return [
                    'ok'            => true,
                    'formation_id'  => (int) $picked->getFormation_id(),
                    'title'         => (string) $picked->getTitle(),
                    'reason'        => $reason,
                    'en_ligne'      => $picked->getEnLigne(),
                    'source'        => 'groq',
                ];
            }
        }

        return $this->fallbackPick($user, $candidates, $participations, $notes);
    }

    /**
     * @param list<Formation> $all
     * @param list<int>       $excludeIds
     *
     * @return list<Formation>
     */
    private function sliceCandidatesExcludingIds(array $all, array $excludeIds, int $max): array
    {
        $exclude = array_flip($excludeIds);
        $out = [];
        foreach ($all as $f) {
            $id = $f->getFormation_id();
            if ($id === null) {
                continue;
            }
            if (isset($exclude[$id])) {
                continue;
            }
            $out[] = $f;
            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param list<Participation> $participations
     *
     * @return list<array<string, mixed>>
     */
    private function buildHistoryPayload(array $participations): array
    {
        $out = [];
        foreach ($participations as $p) {
            $f = $p->getFormation();
            if ($f === null) {
                continue;
            }
            $out[] = [
                'formation_id'   => $f->getFormation_id(),
                'title'          => $f->getTitle(),
                'modalite'       => $f->getEnLigne() ? 'en_ligne' : 'presentiel',
                'lifecycle'      => $p->getStatus(),
                'payment_status' => $p->getPayment_status(),
                'inscrit_le'     => $p->getCreatedAt()?->format('Y-m-d'),
            ];
        }

        return $out;
    }

    /**
     * @param list<Formation> $candidates
     * @param list<int>|null  $anyEnrollmentIds  si défini, ajoute deja_inscription (tout dossier passé)
     *
     * @return list<array<string, mixed>>
     */
    private function buildCandidatesPayload(array $candidates, ?array $anyEnrollmentIds = null): array
    {
        $flags = $anyEnrollmentIds !== null ? array_flip($anyEnrollmentIds) : null;
        $out = [];
        foreach ($candidates as $f) {
            $fid = (int) $f->getFormation_id();
            $row = [
                'formation_id' => $fid,
                'title'        => $f->getTitle(),
                'modalite'     => $f->getEnLigne() ? 'en_ligne' : 'presentiel',
                'resume'       => $this->shortPlainText($f->getDescription(), 220),
                'debut'        => $f->getStartDate()?->format('Y-m-d'),
            ];
            if ($flags !== null) {
                $row['deja_inscription'] = isset($flags[$fid]);
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param list<Formation> $candidates
     *
     * @return array<string, mixed>|null
     */
    private function parseFormationJson(?string $text): ?array
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        try {
            if (preg_match('/\{[\s\S]*\}/u', $text, $matches)) {
                $data = json_decode($matches[0], true, 512, JSON_THROW_ON_ERROR);

                return is_array($data) ? $data : null;
            }
        } catch (\Throwable) {
        }

        return null;
    }

    /**
     * @param list<Formation>        $candidates
     * @param array<string, mixed>   $parsed
     */
    private function resolveCandidate(array $candidates, array $parsed): ?Formation
    {
        $fid = isset($parsed['formation_id']) ? (int) $parsed['formation_id'] : 0;
        $title = isset($parsed['title']) ? trim((string) $parsed['title']) : '';

        foreach ($candidates as $c) {
            if ($c->getFormation_id() === $fid) {
                return $c;
            }
        }

        if ($title !== '') {
            $norm = $this->lower($title);
            foreach ($candidates as $c) {
                $ct = $c->getTitle();
                if ($ct !== null && $this->lower(trim($ct)) === $norm) {
                    return $c;
                }
            }
        }

        return null;
    }

    private function lower(string $s): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
    }

    /** Compare texte sans tenir compte des accents (ex. francais / français). */
    private function foldForMatch(string $input): string
    {
        $lower = $this->lower($input);
        if (!function_exists('iconv')) {
            return $lower;
        }
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $lower);

        return is_string($converted) && $converted !== '' ? $converted : $lower;
    }

    private function shortPlainText(?string $html, int $max): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }
        $plain = trim(strip_tags($html));
        if ($plain === '') {
            return null;
        }
        if (function_exists('mb_strlen') && mb_strlen($plain, 'UTF-8') > $max) {
            return mb_substr($plain, 0, $max, 'UTF-8') . '…';
        }
        if (strlen($plain) > $max) {
            return substr($plain, 0, $max) . '…';
        }

        return $plain;
    }

    /**
     * @param list<Formation>        $candidates
     * @param list<Participation>    $participations
     *
     * @return array{ok: true, formation_id: int, title: string, reason: string, en_ligne: bool, source: string}
     */
    private function fallbackPick(User $user, array $candidates, array $participations, string $notes = ''): array
    {
        if ($notes !== '') {
            $byNotes = $this->pickFormationByNotesOverlap($candidates, $notes);
            if ($byNotes instanceof Formation) {
                return $this->fallbackPayload(
                    $byNotes,
                    $participations,
                    'heuristic_objectifs',
                    $this->reasonForNotesMatch($byNotes, $notes)
                );
            }
        }

        $blocks = $this->formationRecommendationService->getFormationsIndexBlocksForUser($user);
        foreach ($blocks['personalized'] ?? [] as $f) {
            foreach ($candidates as $c) {
                if ((int) $c->getFormation_id() === (int) $f->getFormation_id()) {
                    return $this->fallbackPayload($c, $participations, 'reco_personalized');
                }
            }
        }

        $preferOnline = $this->userPrefersOnline($participations);

        if ($preferOnline !== null) {
            foreach ($candidates as $c) {
                if ($c->getEnLigne() === $preferOnline) {
                    return $this->fallbackPayload($c, $participations, 'heuristic_modalite');
                }
            }
        }

        $first = $candidates[0];

        return $this->fallbackPayload($first, $participations, 'heuristic_default');
    }

    /**
     * @param list<Formation> $candidates
     */
    private function pickFormationByNotesOverlap(array $candidates, string $notes): ?Formation
    {
        $words = $this->tokenizePreferenceText($notes);
        if ($words === []) {
            return null;
        }

        $best = null;
        $bestScore = 0;
        foreach ($candidates as $f) {
            $hay = $this->foldForMatch(trim(($f->getTitle() ?? '') . ' ' . strip_tags((string) $f->getDescription())));
            $score = 0;
            foreach ($words as $w) {
                if ($w === '') {
                    continue;
                }
                $wf = $this->foldForMatch($w);
                if ($wf !== '' && str_contains($hay, $wf)) {
                    ++$score;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $f;
            }
        }

        return $bestScore > 0 ? $best : null;
    }

    /**
     * @return list<string>
     */
    private function tokenizePreferenceText(string $notes): array
    {
        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $this->lower($notes)) ?? '';
        $parts = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stop = ['le', 'la', 'les', 'un', 'une', 'des', 'de', 'du', 'et', 'ou', 'en', 'au', 'aux', 'pour', 'sur', 'dans', 'avec', 'sans', 'plus', 'the', 'a', 'an', 'and', 'or', 'to', 'of', 'in', 'on'];
        $out = [];
        foreach ($parts as $p) {
            $len = function_exists('mb_strlen') ? mb_strlen($p, 'UTF-8') : strlen($p);
            if ($len < 2) {
                continue;
            }
            if (in_array($p, $stop, true)) {
                continue;
            }
            $out[] = $p;
        }

        return array_values(array_unique($out));
    }

    private function reasonForNotesMatch(Formation $formation, string $notes): string
    {
        $t = $formation->getTitle() ?? '';

        return 'Cette formation ressort comme la plus proche de vos objectifs (« '
            . $this->shortPlainText($notes, 120)
            . ' »), notamment via son titre ou son contenu : « '
            . $this->shortPlainText($t, 80)
            . ' », en cohérence avec votre historique sur la plateforme.';
    }

    /**
     * @param list<Participation> $participations
     */
    private function fallbackPayload(Formation $formation, array $participations, string $source, ?string $reasonOverride = null): array
    {
        if ($reasonOverride !== null && trim($reasonOverride) !== '') {
            $reason = trim($reasonOverride);
        } else {
            $reason = 'Suggestion basée sur votre historique et les formations encore disponibles à l’inscription.';
            if ($participations === []) {
                $reason = 'Vous débutez sur la plateforme : nous vous proposons une session adaptée au catalogue actuel.';
            }
        }

        return [
            'ok'            => true,
            'formation_id'  => (int) $formation->getFormation_id(),
            'title'         => (string) $formation->getTitle(),
            'reason'        => $reason,
            'en_ligne'      => $formation->getEnLigne(),
            'source'        => $source,
        ];
    }

    /**
     * @param list<Participation> $participations
     */
    private function userPrefersOnline(array $participations): ?bool
    {
        $paid = array_values(array_filter($participations, static fn (Participation $p): bool => $p->isPaidEnrollment()));

        if ($paid === []) {
            return null;
        }

        $online = 0;
        foreach ($paid as $p) {
            $f = $p->getFormation();
            if ($f !== null && $f->getEnLigne()) {
                ++$online;
            }
        }

        if ($online * 2 >= count($paid)) {
            return true;
        }
        if (($online * 2) <= count($paid)) {
            return false;
        }

        return null;
    }
}
