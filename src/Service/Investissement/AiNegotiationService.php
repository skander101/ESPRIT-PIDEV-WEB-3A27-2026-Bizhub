<?php

namespace App\Service\Investissement;

use App\Entity\Investissement\Negotiation;
use App\Entity\Investissement\NegotiationMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Riche analyse d'une négociation via OpenAI GPT-4o-mini.
 * Retourne toujours un résultat (fallback local si l'API est indisponible).
 *
 * Structure retournée :
 *   score                int 0-100
 *   recommendation       "Investir"|"Négocier"|"Décliner"
 *   risk_level           "Faible"|"Modéré"|"Élevé"
 *   sub_scores           { rentabilite, risque_maitrise, qualite_negociation, potentiel_projet, coherence_budget }
 *   resume               string — paragraphe de synthèse
 *   action_plan          string[] — 3-4 actions concrètes
 *   badges               string[] — max 3 badges contextuels
 *   strengths            string[] — 2-3 points forts
 *   weaknesses           string[] — 2-3 points à surveiller
 *   draft_message        string — brouillon standard
 *   draft_pro            string — version professionnelle
 *   draft_diplomatic     string — version diplomatique
 *   draft_direct         string — version directe
 *   source               "openai"|"local"
 *   fallback_reason      string|null
 */
class AiNegotiationService
{
    private const OPENAI_URL = 'https://api.openai.com/v1/chat/completions';
    private const MODEL      = 'gpt-4o-mini';
    private const TIMEOUT    = 18;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string              $openaiApiKey,
    ) {}

    // ── Public ────────────────────────────────────────────────────────────────

    /**
     * Dispatch vers l'analyse investisseur ou startup selon le rôle.
     * @param string $userType  'investor' | 'startup'
     */
    public function analyse(Negotiation $neg, array $messages, string $userType = 'investor'): array
    {
        return $userType === 'startup'
            ? $this->analyzeForStartup($neg, $messages)
            : $this->analyzeForInvestor($neg, $messages);
    }

    /**
     * Analyse décisionnelle côté investisseur :
     * score, recommandation, risque, radar, sous-scores, brouillons.
     */
    public function analyzeForInvestor(Negotiation $neg, array $messages): array
    {
        if (empty($this->openaiApiKey) || str_starts_with($this->openaiApiKey, 'your_')) {
            return $this->localAnalysis($neg, $messages, 'Clé API non configurée');
        }
        try {
            $result = $this->callOpenAi($neg, $messages);
            $result['user_type']       = 'investor';
            $result['source']          = 'openai';
            $result['fallback_reason'] = null;
            return $result;
        } catch (\Throwable $e) {
            return $this->localAnalysis($neg, $messages, $e->getMessage());
        }
    }

    /**
     * Analyse coaching côté startup :
     * pourquoi l'investisseur hésite, points faibles, suggestions,
     * conseils pour améliorer l'offre, message recommandé.
     */
    public function analyzeForStartup(Negotiation $neg, array $messages): array
    {
        if (empty($this->openaiApiKey) || str_starts_with($this->openaiApiKey, 'your_')) {
            return $this->localStartupAnalysis($neg, $messages, 'Clé API non configurée');
        }
        try {
            $result = $this->callOpenAiStartup($neg, $messages);
            $result['user_type']       = 'startup';
            $result['source']          = 'openai';
            $result['fallback_reason'] = null;
            return $result;
        } catch (\Throwable $e) {
            return $this->localStartupAnalysis($neg, $messages, $e->getMessage());
        }
    }

    // ── OpenAI ────────────────────────────────────────────────────────────────

    private function callOpenAi(Negotiation $neg, array $messages): array
    {
        [$system, $user] = $this->buildPrompts($neg, $messages);

        $response = $this->httpClient->request('POST', self::OPENAI_URL, [
            'headers' => ['Authorization' => 'Bearer ' . $this->openaiApiKey, 'Content-Type' => 'application/json'],
            'timeout' => self::TIMEOUT,
            'json'    => [
                'model'       => self::MODEL,
                'temperature' => 0.35,
                'max_tokens'  => 1200,
                'messages'    => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user',   'content' => $user],
                ],
            ],
        ]);

        $code = $response->getStatusCode();
        if ($code === 429) throw new \RuntimeException('quota_exceeded');
        if ($code === 401 || $code === 403) throw new \RuntimeException('invalid_key');
        if ($code >= 500) throw new \RuntimeException('openai_unavailable');
        if ($code >= 400) throw new \RuntimeException('api_error_' . $code);

        $data    = $response->toArray();
        $content = $data['choices'][0]['message']['content'] ?? '{}';
        $content = preg_replace('/^```(?:json)?\s*/i', '', trim($content));
        $content = preg_replace('/\s*```$/s', '', $content);

        $result = json_decode($content, true);
        if (!is_array($result) || !isset($result['score'])) {
            throw new \RuntimeException('invalid_response');
        }

        return $this->sanitize($result);
    }

    private function buildPrompts(Negotiation $neg, array $messages): array
    {
        $project  = $neg->getProject();
        $proposed = number_format((float) $neg->getProposed_amount(), 0, ',', ' ');
        $budget   = $project ? number_format((float) $project->getRequiredBudget(), 0, ',', ' ') : '?';
        $secteur  = $project?->getSecteur() ?? 'Non précisé';
        $desc     = mb_substr($project?->getDescription() ?? 'Aucune description', 0, 400);

        $chat = '';
        foreach (array_slice($messages, -12) as $msg) {
            $role   = ($msg->getUser()?->getUserId() === $neg->getInvestor()?->getUserId()) ? 'Investisseur' : 'Startup';
            $amount = $msg->getProposed_amount() ? ' [Offre: ' . number_format((float)$msg->getProposed_amount(), 0, ',', ' ') . ' TND]' : '';
            $chat  .= "{$role}: {$msg->getMessage()}{$amount}\n";
        }

        $system = <<<PROMPT
Tu es un expert senior en investissement startup et analyse financière, spécialisé dans l'écosystème tunisien (BizHub).
Ta mission : analyser une négociation d'investissement et produire un rapport structuré, factuel et exploitable.

Règles absolues :
- Réponds UNIQUEMENT en JSON valide, sans markdown, sans bloc ```, sans commentaire
- Tous les textes en français
- Sois précis, professionnel, nuancé — pas de généralités creuses
- Les brouillons doivent être directement copiables et envoyables

Structure JSON exacte (respecte chaque clé) :
{
  "score": <entier 0-100 — évaluation globale du potentiel d'investissement>,
  "recommendation": <"Investir"|"Négocier"|"Décliner">,
  "risk_level": <"Faible"|"Modéré"|"Élevé">,
  "sub_scores": {
    "rentabilite": <0-100 — potentiel de rendement>,
    "risque_maitrise": <0-100 — niveau de maîtrise des risques>,
    "qualite_negociation": <0-100 — qualité et engagement des échanges>,
    "potentiel_projet": <0-100 — solidité et potentiel du projet>,
    "coherence_budget": <0-100 — adéquation montant proposé / besoins>
  },
  "resume": "<2-3 phrases synthétiques, professionnelles, contextualisées à ce projet spécifique>",
  "action_plan": ["<action concrète 1>", "<action concrète 2>", "<action concrète 3>", "<action 4 optionnelle>"],
  "badges": ["<badge1>", "<badge2>", "<badge3 optionnel — max 3>"],
  "strengths": ["<point fort concret 1>", "<point fort 2>", "<point fort 3 optionnel>"],
  "weaknesses": ["<point à surveiller 1>", "<point à surveiller 2>", "<point à surveiller 3 optionnel>"],
  "draft_pro": "<message formel et structuré avec formule de politesse — style investisseur institutionnel>",
  "draft_diplomatic": "<message ouvert, bienveillant, qui ménage la relation — style partenariat>",
  "draft_direct": "<message court et factuel, sans fioritures — 2-3 phrases max>",
  "draft_persuasif": "<message enthousiaste et convaincant, valorise le projet, pousse vers une conclusion positive — sans être pressant>"
}

Barème scores :
- score >= 72 → Investir, risque Faible
- 45 <= score < 72 → Négocier, risque Modéré
- score < 45 → Décliner, risque Élevé

Badges autorisés (max 3) : "Opportunité prometteuse", "Budget insuffisant", "Accord proche", "Négociation active", "Risqué", "À surveiller", "Projet mature", "Potentiel élevé", "Discussion bloquée", "Budget cohérent", "Secteur porteur", "Manque d'informations", "Montant faible", "Engagement fort".
PROMPT;

        $user = "Projet: {$project?->getTitle()} | Secteur: {$secteur} | Budget requis: {$budget} TND\nDescription: {$desc}\n\nNégociation: montant proposé {$proposed} TND | statut: {$neg->getStatus()}\n\nConversation:\n{$chat}";

        return [$system, $user];
    }

    // ── Local fallback ────────────────────────────────────────────────────────

    private function localAnalysis(Negotiation $neg, array $messages, string $errorCode): array
    {
        $project  = $neg->getProject();
        $proposed = (float) $neg->getProposed_amount();
        $budget   = $project ? (float) $project->getRequiredBudget() : 0;
        $secteur  = mb_strtolower($project?->getSecteur() ?? '');
        $title    = $project?->getTitle() ?? 'ce projet';
        $msgCount = count($messages);

        $ratio    = ($budget > 0) ? ($proposed / $budget) : 0;

        // ── Sub-scores ──────────────────────────────────────────────────────

        // Rentabilité : dépend du ratio couverture + secteur
        $rentabilite = 35;
        if ($ratio >= 1.0)     $rentabilite = 80;
        elseif ($ratio >= 0.7) $rentabilite = 65;
        elseif ($ratio >= 0.4) $rentabilite = 50;
        elseif ($ratio >= 0.1) $rentabilite = 40;
        if ($this->isHotSector($secteur)) $rentabilite = min(95, $rentabilite + 12);

        // Risque maîtrisé : inversement proportionnel au risque réel
        $risque = 40;
        if ($msgCount >= 8)      $risque += 20;
        elseif ($msgCount >= 4)  $risque += 10;
        if ($proposed > 0 && $budget > 0) {
            if ($ratio > 0.8 && $ratio < 1.5) $risque += 15;
            elseif ($ratio > 2.0 || $ratio < 0.1) $risque -= 10;
        }
        if ($this->hasOfferMessages($messages)) $risque += 10;
        $risque = max(10, min(90, $risque));

        // Qualité négociation : engagement + offres concrètes
        $qualiteNeg = 30;
        if ($msgCount >= 10)    $qualiteNeg = 85;
        elseif ($msgCount >= 6) $qualiteNeg = 70;
        elseif ($msgCount >= 3) $qualiteNeg = 52;
        elseif ($msgCount >= 1) $qualiteNeg = 38;
        if ($this->hasOfferMessages($messages)) $qualiteNeg = min(95, $qualiteNeg + 12);

        // Potentiel projet
        $potentiel = 45;
        if ($this->isHotSector($secteur)) $potentiel += 20;
        if ($budget > 0 && $budget < 500000)  $potentiel += 10; // early stage
        if ($project?->getDescription() && strlen($project->getDescription()) > 150) $potentiel += 10;
        $potentiel = min(90, $potentiel);

        // Cohérence budget
        $coherence = 50;
        if ($budget <= 0)           { $coherence = 20; }
        elseif ($ratio >= 0.3 && $ratio <= 1.5) { $coherence = 75; }
        elseif ($ratio > 1.5)       { $coherence = 40; } // surfinancement suspect
        elseif ($ratio < 0.1)       { $coherence = 30; }
        elseif ($ratio >= 0.1)      { $coherence = 55; }

        // ── Score global (moyenne pondérée) ─────────────────────────────────
        $score = (int) round(
            $rentabilite * 0.25 +
            $risque      * 0.20 +
            $qualiteNeg  * 0.20 +
            $potentiel   * 0.20 +
            $coherence   * 0.15
        );
        $score = max(5, min(95, $score));

        // ── Recommandation & risque ─────────────────────────────────────────
        // Seuils alignés avec le prompt OpenAI : >= 72 = Investir, >= 45 = Négocier
        if ($score >= 72)      { $rec = 'Investir';  $riskLvl = 'Faible'; }
        elseif ($score >= 45)  { $rec = 'Négocier';  $riskLvl = 'Modéré'; }
        else                   { $rec = 'Décliner';  $riskLvl = 'Élevé'; }

        // ── Badges ──────────────────────────────────────────────────────────
        $badges = [];
        if ($score >= 70)           $badges[] = 'Opportunité prometteuse';
        if ($ratio < 0.15 && $proposed > 0) $badges[] = 'Budget insuffisant';
        if ($score >= 65 && $msgCount >= 5) $badges[] = 'Accord proche';
        if ($msgCount >= 6)         $badges[] = 'Négociation active';
        if ($riskLvl === 'Élevé')   $badges[] = 'Risqué';
        if ($this->isHotSector($secteur)) $badges[] = 'Secteur porteur';
        if ($msgCount < 2)          $badges[] = 'Discussion insuffisante';
        if ($proposed <= 0)         $badges[] = 'Manque d\'informations';
        if ($ratio >= 0.4 && $ratio <= 1.2) $badges[] = 'Budget cohérent';
        $badges = array_slice($badges, 0, 3);

        // ── Résumé ──────────────────────────────────────────────────────────
        $proposedFmt = $proposed > 0 ? number_format($proposed, 0, ',', ' ') . ' TND' : 'non défini';
        $budgetFmt   = $budget > 0   ? number_format($budget,   0, ',', ' ') . ' TND' : 'non renseigné';
        $coverageStr = ($budget > 0 && $proposed > 0) ? sprintf('(%.0f%% du budget requis)', $ratio * 100) : '';

        $resume = $this->buildResume($rec, $riskLvl, $title, $proposedFmt, $budgetFmt, $coverageStr, $msgCount, $secteur);

        // ── Points forts / Points faibles ────────────────────────────────────
        $strengths  = $this->buildStrengths($ratio, $proposed, $budget, $msgCount, $secteur, $rec);
        $weaknesses = $this->buildWeaknesses($ratio, $proposed, $budget, $msgCount, $secteur, $rec);

        // ── Plan d'action ────────────────────────────────────────────────────
        $actionPlan = $this->buildActionPlan($rec, $ratio, $proposed, $budget, $msgCount, $riskLvl);

        // ── Brouillons ───────────────────────────────────────────────────────
        $drafts = $this->buildDrafts($rec, $title, $proposed, $budget);

        return [
            'user_type'        => 'investor',
            'score'            => $score,
            'recommendation'   => $rec,
            'risk_level'       => $riskLvl,
            'sub_scores'       => [
                'rentabilite'         => $rentabilite,
                'risque_maitrise'     => $risque,
                'qualite_negociation' => $qualiteNeg,
                'potentiel_projet'    => $potentiel,
                'coherence_budget'    => $coherence,
            ],
            'resume'           => $resume,
            'action_plan'      => $actionPlan,
            'badges'           => $badges,
            'strengths'        => $strengths,
            'weaknesses'       => $weaknesses,
            'draft_pro'        => $drafts['pro'],
            'draft_diplomatic' => $drafts['diplomatic'],
            'draft_direct'     => $drafts['direct'],
            'draft_persuasif'  => $drafts['persuasif'],
            'source'           => 'local',
            'fallback_reason'  => $this->humanizeError($errorCode),
        ];
    }

    // ── Build helpers ─────────────────────────────────────────────────────────

    private function buildResume(string $rec, string $risk, string $title, string $proposed, string $budget, string $coverage, int $msgs, string $secteur): string
    {
        $sectorStr = $secteur ? " dans le secteur {$secteur}" : '';
        if ($rec === 'Investir') {
            return "Le projet « {$title} »{$sectorStr} présente un profil d'investissement solide avec un montant proposé de {$proposed} {$coverage}. Les échanges menés ({$msgs} messages) révèlent un intérêt mutuel sérieux et des conditions favorables. Le niveau de risque est jugé {$risk} — les conditions semblent réunies pour envisager une finalisation.";
        }
        if ($rec === 'Négocier') {
            return "Le projet « {$title} »{$sectorStr} montre un potentiel réel mais nécessite encore quelques clarifications avant de s'engager. Le montant proposé est de {$proposed} pour un budget requis de {$budget} {$coverage}. Avec {$msgs} échanges au compteur, la discussion est en bonne voie mais des points clés restent à préciser pour réduire le niveau de risque ({$risk}).";
        }
        return "Le projet « {$title} »{$sectorStr} présente actuellement trop d'incertitudes pour recommander un investissement. Le montant proposé de {$proposed} et les {$msgs} échanges enregistrés ne permettent pas encore d'évaluer la viabilité avec confiance. Un niveau de risque {$risk} invite à la prudence — davantage d'informations sont nécessaires avant toute décision.";
    }

    private function buildStrengths(float $ratio, float $proposed, float $budget, int $msgs, string $secteur, string $rec): array
    {
        $strengths = [];

        if ($proposed > 0 && $ratio >= 1.0) {
            $strengths[] = sprintf('Montant proposé (%s TND) couvrant intégralement le budget requis — signal fort d\'engagement.', number_format($proposed, 0, ',', ' '));
        } elseif ($proposed > 0 && $ratio >= 0.5) {
            $strengths[] = sprintf('Couverture solide à %.0f%% du budget requis — base de négociation sérieuse.', $ratio * 100);
        } elseif ($proposed > 0 && $ratio >= 0.3) {
            $strengths[] = sprintf('Montant proposé représentant %.0f%% du budget — point de départ concret pour avancer.', $ratio * 100);
        }

        if ($msgs >= 8) {
            $strengths[] = "Négociation très active ({$msgs} échanges) — engagement élevé et sérieux des deux parties.";
        } elseif ($msgs >= 4) {
            $strengths[] = "Discussion engagée ({$msgs} messages) — dynamique positive confirmée.";
        }

        if ($this->isHotSector($secteur)) {
            $strengths[] = ucfirst("Secteur « {$secteur} » porteur et en forte croissance — potentiel de valorisation élevé.");
        }

        if ($rec === 'Investir') {
            $strengths[] = "Profil global favorable : les critères clés sont alignés pour envisager une finalisation.";
        }

        if (empty($strengths)) {
            $strengths[] = "Projet présentant un potentiel à confirmer avec davantage d'informations.";
        }

        return array_slice($strengths, 0, 3);
    }

    private function buildWeaknesses(float $ratio, float $proposed, float $budget, int $msgs, string $secteur, string $rec): array
    {
        $weaknesses = [];

        if ($proposed <= 0) {
            $weaknesses[] = "Aucun montant précis proposé — clarification urgente nécessaire pour progresser.";
        } elseif ($ratio < 0.15) {
            $weaknesses[] = sprintf('Montant proposé très faible (%.0f%% du budget requis) — gap financier significatif à combler.', $ratio * 100);
        } elseif ($ratio > 1.8) {
            $weaknesses[] = sprintf('Montant proposé bien au-delà du budget déclaré (%.0f%%) — vérifiez la cohérence des chiffres.', $ratio * 100);
        }

        if ($msgs < 2) {
            $weaknesses[] = "Discussion très limitée ({$msgs} message(s)) — impossible d'évaluer l'intérêt réel sans davantage d'échanges.";
        } elseif ($msgs < 4 && $rec !== 'Investir') {
            $weaknesses[] = "Peu d'échanges ({$msgs} messages) — approfondissez la discussion avant de prendre une décision.";
        }

        if (!$this->isHotSector($secteur) && $secteur) {
            $weaknesses[] = "Secteur moins dynamique — analysez la différenciation concurrentielle avant tout engagement.";
        }

        if ($budget > 0 && $ratio < 1.0) {
            $weaknesses[] = sprintf('Financement partiel du budget requis (%s TND) — vérifiez si des co-investisseurs complètent le tour.', number_format($budget, 0, ',', ' '));
        }

        if ($rec === 'Décliner') {
            $weaknesses[] = "Score insuffisant sur plusieurs critères — risque trop élevé en l'état actuel des informations.";
        }

        if (empty($weaknesses)) {
            $weaknesses[] = "Restez vigilant sur les conditions contractuelles et la dilution de participation.";
        }

        return array_slice($weaknesses, 0, 3);
    }

    private function buildActionPlan(string $rec, float $ratio, float $proposed, float $budget, int $msgs, string $risk): array
    {
        $plan = [];
        if ($proposed <= 0) {
            $plan[] = "Proposer un montant d'investissement concret pour faire avancer la négociation.";
        } elseif ($ratio < 0.5) {
            $plan[] = sprintf('Envisager d\'augmenter le montant proposé (actuellement %.0f%% du budget requis).', $ratio * 100);
        }
        if ($msgs < 4) {
            $plan[] = "Approfondir les échanges avec la startup pour mieux qualifier le projet.";
        }
        if ($rec === 'Négocier') {
            $plan[] = "Demander un plan détaillé d'utilisation des fonds et les projections financières.";
            $plan[] = "Clarifier les conditions de la prise de participation et les droits associés.";
        } elseif ($rec === 'Investir') {
            $plan[] = "Préparer une lettre d'intention (LOI) pour formaliser l'engagement.";
            $plan[] = "Convenir d'un calendrier de clôture et des conditions suspensives.";
        } else {
            $plan[] = "Demander davantage d'informations sur la traction et les métriques clés.";
            $plan[] = "Réévaluer après obtention d'un plan de développement complet.";
        }
        if ($risk === 'Élevé') {
            $plan[] = "Envisager un investissement par tranches conditionnées à des jalons de performance.";
        }
        if (empty($plan)) {
            $plan[] = "Poursuivre les échanges pour affiner les conditions finales.";
        }
        return array_slice($plan, 0, 4);
    }

    private function buildDrafts(string $rec, string $title, float $proposed, float $budget): array
    {
        $amtStr = $proposed > 0 ? number_format($proposed, 0, ',', ' ') . ' TND' : number_format($budget * 0.5, 0, ',', ' ') . ' TND';

        if ($rec === 'Investir') {
            return [
                'pro'        => "Madame, Monsieur,\n\nÀ l'issue de notre processus d'évaluation, je suis en mesure de vous confirmer mon intérêt ferme pour un investissement de {$amtStr} dans le projet {$title}.\n\nJe souhaite planifier un appel cette semaine afin de définir les modalités contractuelles et le calendrier de closing.\n\nCordialement.",
                'diplomatic' => "Bonjour,\n\nNos échanges m'ont convaincu de la solidité de votre projet {$title}. Je souhaite avancer positivement et suis prêt à envisager un engagement de {$amtStr}, sous réserve de quelques précisions sur le cadre juridique de la participation.\n\nQuand seriez-vous disponible pour un appel ?",
                'direct'     => "Je suis prêt à investir {$amtStr} dans {$title}. Envoyez-moi les documents de clôture pour signature.",
                'persuasif'  => "Votre projet {$title} m'a réellement convaincu — la vision est claire et le potentiel est indéniable. Je suis prêt à m'engager pour {$amtStr} et je suis persuadé que nous pouvons construire quelque chose de solide ensemble. Quand pouvons-nous finaliser les conditions et passer à l'étape suivante ?",
            ];
        }
        if ($rec === 'Négocier') {
            return [
                'pro'        => "Madame, Monsieur,\n\nLe projet {$title} présente des caractéristiques qui retiennent mon attention. Afin de progresser vers un accord, je vous serais reconnaissant de bien vouloir me communiquer les éléments suivants : détail budgétaire, projections sur 3 ans et conditions de la participation.\n\nDans l'attente de votre retour, je reste à votre disposition.\n\nBien cordialement.",
                'diplomatic' => "Bonjour,\n\nJe suis sincèrement intéressé par {$title} et souhaite que nous puissions construire ensemble les meilleures conditions d'investissement. Pour cela, il me serait utile de comprendre comment les {$amtStr} seraient alloués et quelle participation vous envisagez. Ces précisions me permettront de confirmer rapidement ma position.",
                'direct'     => "Intéressé par {$title} pour {$amtStr}. Besoin du détail budget + conditions de participation. Envoyez svp.",
                'persuasif'  => "Le projet {$title} m'intéresse vraiment et je vois un réel potentiel dans votre approche. Avec quelques précisions supplémentaires, je serais en mesure de confirmer mon engagement sur {$amtStr}. Je suis convaincu que nous pouvons trouver des conditions gagnant-gagnant — donnons-nous les moyens d'aller jusqu'au bout.",
            ];
        }
        return [
            'pro'        => "Madame, Monsieur,\n\nJe vous remercie pour les échanges que nous avons eus autour du projet {$title}. Cependant, je ne suis pas en mesure de prendre une décision d'investissement en l'état actuel des informations disponibles.\n\nJe vous invite à me communiquer un dossier complet incluant vos métriques de traction, votre modèle économique détaillé et vos projections financières. Je réévaluerai votre dossier à réception.\n\nCordialement.",
            'diplomatic' => "Bonjour,\n\nJe vous remercie pour votre implication dans cette discussion. Le projet {$title} suscite mon intérêt, mais certains aspects nécessitent encore des clarifications pour que je puisse m'engager sereinement. Pourriez-vous me communiquer des informations supplémentaires sur votre traction et votre stratégie de croissance ?",
            'direct'     => "Pas encore convaincu pour {$title}. Besoin de : métriques de traction, prévisions financières sur 3 ans, et détail des charges. Recontact après réception.",
            'persuasif'  => "Le projet {$title} soulève des interrogations légitimes, mais l'opportunité n'est pas fermée. Si vous pouvez me convaincre sur la traction, les projections et l'utilisation des fonds, je suis sincèrement ouvert à reconsidérer ma position. Convainquez-moi — je suis à l'écoute.",
        ];
    }

    // ── Startup : OpenAI ──────────────────────────────────────────────────────

    private function callOpenAiStartup(Negotiation $neg, array $messages): array
    {
        [$system, $user] = $this->buildStartupPrompts($neg, $messages);

        $response = $this->httpClient->request('POST', self::OPENAI_URL, [
            'headers' => ['Authorization' => 'Bearer ' . $this->openaiApiKey, 'Content-Type' => 'application/json'],
            'timeout' => self::TIMEOUT,
            'json'    => [
                'model'       => self::MODEL,
                'temperature' => 0.4,
                'max_tokens'  => 1200,
                'messages'    => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user',   'content' => $user],
                ],
            ],
        ]);

        $code = $response->getStatusCode();
        if ($code === 429) throw new \RuntimeException('quota_exceeded');
        if ($code === 401 || $code === 403) throw new \RuntimeException('invalid_key');
        if ($code >= 500) throw new \RuntimeException('openai_unavailable');
        if ($code >= 400) throw new \RuntimeException('api_error_' . $code);

        $data    = $response->toArray();
        $content = $data['choices'][0]['message']['content'] ?? '{}';
        $content = preg_replace('/^```(?:json)?\s*/i', '', trim($content));
        $content = preg_replace('/\s*```$/s', '', $content);

        $result = json_decode($content, true);
        if (!is_array($result) || !isset($result['coach_summary'])) {
            throw new \RuntimeException('invalid_response');
        }

        return $this->sanitizeStartup($result);
    }

    private function buildStartupPrompts(Negotiation $neg, array $messages): array
    {
        $project  = $neg->getProject();
        $proposed = number_format((float) $neg->getProposed_amount(), 0, ',', ' ');
        $budget   = $project ? number_format((float) $project->getRequiredBudget(), 0, ',', ' ') : '?';
        $secteur  = $project?->getSecteur() ?? 'Non précisé';
        $desc     = mb_substr($project?->getDescription() ?? 'Aucune description', 0, 400);

        $chat = '';
        foreach (array_slice($messages, -12) as $msg) {
            $role   = ($msg->getUser()?->getUserId() === $neg->getInvestor()?->getUserId()) ? 'Investisseur' : 'Startup';
            $amount = $msg->getProposed_amount() ? ' [Offre: ' . number_format((float)$msg->getProposed_amount(), 0, ',', ' ') . ' TND]' : '';
            $chat  .= "{$role}: {$msg->getMessage()}{$amount}\n";
        }

        $system = <<<PROMPT
Tu es un mentor expert en négociation d'investissement et en développement de startups tunisiennes (BizHub).
Ta mission : analyser une négociation du point de vue de la STARTUP et lui donner des conseils pratiques pour améliorer sa position et convaincre l'investisseur.
Tu parles directement à la startup — utilise "vous" — avec bienveillance, précision et pragmatisme.

Règles absolues :
- Réponds UNIQUEMENT en JSON valide, sans markdown, sans bloc ```, sans commentaire
- Tous les textes en français
- Sois concret, actionnable, et encourageant — jamais condescendant
- Le message recommandé doit être directement copiable et envoyable
- Focus sur l'amélioration, pas sur le jugement

Structure JSON exacte (respecte chaque clé) :
{
  "coach_summary": "<2-3 phrases bienveillantes résumant la situation pour la startup et son potentiel>",
  "hesitation_reasons": ["<raison concrète 1 pour laquelle l'investisseur hésite>", "<raison 2>", "<raison 3 optionnelle>"],
  "weak_points": ["<point faible identifié dans la négociation 1>", "<point faible 2>", "<point faible 3 optionnel>"],
  "suggestions": ["<suggestion concrète et actionnable 1>", "<suggestion 2>", "<suggestion 3>", "<suggestion 4 optionnelle>"],
  "improvement_tips": ["<conseil spécifique pour améliorer l'offre ou la présentation 1>", "<conseil 2>", "<conseil 3>"],
  "recommended_message": "<message complet et professionnel à envoyer à l'investisseur pour relancer positivement la négociation — style startup ambitieuse et sérieuse>",
  "action_steps": ["<prochaine action prioritaire 1>", "<action 2>", "<action 3>"]
}
PROMPT;

        $user = "Projet: {$project?->getTitle()} | Secteur: {$secteur} | Budget requis: {$budget} TND | Montant proposé par l'investisseur: {$proposed} TND\nDescription: {$desc}\n\nStatut négociation: {$neg->getStatus()}\n\nConversation:\n{$chat}";

        return [$system, $user];
    }

    // ── Startup : Fallback local ──────────────────────────────────────────────

    private function localStartupAnalysis(Negotiation $neg, array $messages, string $errorCode): array
    {
        $project  = $neg->getProject();
        $proposed = (float) $neg->getProposed_amount();
        $budget   = $project ? (float) $project->getRequiredBudget() : 0;
        $title    = $project?->getTitle() ?? 'votre projet';
        $secteur  = $project?->getSecteur() ?? '';
        $msgCount = count($messages);
        $ratio    = ($budget > 0 && $proposed > 0) ? ($proposed / $budget) : 0;

        // ── Raisons d'hésitation de l'investisseur ───────────────────────────
        $hesitations = [];
        if ($proposed <= 0) {
            $hesitations[] = "Aucun montant d'investissement précis n'a encore été proposé — l'investisseur attend une offre chiffrée.";
        } elseif ($ratio < 0.25) {
            $hesitations[] = sprintf("Le montant proposé (%.0f%% du budget requis) est trop bas pour couvrir les besoins opérationnels du projet.", $ratio * 100);
        } elseif ($ratio > 2.0) {
            $hesitations[] = "Le montant proposé semble disproportionné par rapport au budget déclaré — vérifiez la cohérence de vos chiffres.";
        }
        if ($msgCount < 3) {
            $hesitations[] = "La discussion est trop limitée pour que l'investisseur ait une vision claire du projet et de ses porteurs.";
        }
        if (empty(trim($project?->getDescription() ?? ''))) {
            $hesitations[] = "La description du projet est insuffisante — l'investisseur manque d'informations pour évaluer le potentiel.";
        }
        $hesitations[] = "L'investisseur cherche à réduire son risque — il attend des preuves concrètes de traction ou de viabilité.";
        if (!$this->isHotSector($secteur)) {
            $hesitations[] = "Le secteur est perçu comme moins dynamique — il faut mettre en avant les éléments de différenciation.";
        }
        $hesitations = array_slice($hesitations, 0, 3);

        // ── Points faibles de la négociation ────────────────────────────────
        $weakPoints = [];
        if ($msgCount < 3) {
            $weakPoints[] = "Trop peu d'échanges ({$msgCount} message(s)) — la relation n'est pas encore assez développée pour convaincre.";
        }
        if ($ratio > 0 && $ratio < 0.4) {
            $weakPoints[] = sprintf("L'offre couvre seulement %.0f%% du budget requis — l'écart est trop important pour être ignoré.", $ratio * 100);
        }
        if (!$this->hasOfferMessages($messages)) {
            $weakPoints[] = "Aucune contre-offre structurée n'a été formulée — renforcez votre position avec des données chiffrées.";
        }
        $weakPoints[] = "La valeur unique du projet n'est pas suffisamment mise en avant dans les échanges.";
        if ($budget <= 0) {
            $weakPoints[] = "Le budget requis n'est pas clairement défini — cela fragilise la crédibilité de votre dossier.";
        }
        $weakPoints = array_slice($weakPoints, 0, 3);

        // ── Suggestions concrètes ────────────────────────────────────────────
        $suggestions = [];
        $suggestions[] = "Préparez un pitch deck de 10 slides incluant : problème, solution, marché, traction, équipe, finances et utilisation des fonds.";
        if ($ratio < 0.5 && $proposed > 0) {
            $suggestions[] = sprintf("Proposez un plan de financement par étapes : montant initial de %s TND + tranche conditionnée à des jalons de performance.", number_format($proposed, 0, ',', ' '));
        }
        $suggestions[] = "Partagez vos métriques de traction : nombre d'utilisateurs, CA, clients signés ou lettres d'intention.";
        $suggestions[] = "Détaillez l'utilisation exacte des fonds : % par poste (tech, marketing, recrutement, etc.).";
        $suggestions = array_slice($suggestions, 0, 4);

        // ── Conseils d'amélioration de l'offre ───────────────────────────────
        $tips = [];
        $tips[] = "Réduisez le risque perçu : proposez un KYC (Know Your Startup) complet avec documents légaux et comptables à jour.";
        $tips[] = "Montrez la traction : témoignages clients, lettres d'intention, partenariats signés, ou données d'utilisation.";
        if ($ratio < 1.0 && $proposed > 0) {
            $tips[] = "Envisagez d'offrir une participation légèrement supérieure pour compenser l'écart entre le montant proposé et le budget requis.";
        }
        $tips = array_slice($tips, 0, 3);

        // ── Message recommandé ───────────────────────────────────────────────
        $proposedFmt = $proposed > 0 ? number_format($proposed, 0, ',', ' ') . ' TND' : 'à définir';
        $budgetFmt   = $budget   > 0 ? number_format($budget,   0, ',', ' ') . ' TND' : 'à préciser';

        $recommended = "Bonjour,\n\nMerci pour votre intérêt pour {$title}. Suite à nos échanges, je souhaite vous partager quelques éléments supplémentaires pour avancer ensemble.\n\nNotre projet répond à [précisez le problème concret], avec une solution différenciante qui nous permet [précisez la valeur unique]. À ce jour, [ajoutez un élément de traction : x clients, x € de CA, x lettres d'intention].\n\nConcernant le financement, votre proposition de {$proposedFmt} couvrirait [indiquez ce que cela finance]. Nous serions également ouverts à discuter d'un montage par tranches lié à des jalons clairs.\n\nJe serais ravi(e) d'organiser un appel cette semaine pour répondre à vos questions et vous présenter notre plan détaillé.\n\nCordialement,\n[Votre nom] — {$title}";

        // ── Prochaines actions ───────────────────────────────────────────────
        $actionSteps = [
            "Envoyer le message recommandé à l'investisseur aujourd'hui.",
            "Préparer un one-pager avec les métriques clés et l'utilisation des fonds.",
            "Proposer un appel vidéo dans les 48h pour présenter le projet en détail.",
        ];

        $coachSummary = sprintf(
            'Votre négociation pour %s en est à %d échanges. Le potentiel est réel, mais quelques ajustements dans votre approche peuvent significativement renforcer votre position. Avec les bons arguments et une communication proactive, vous pouvez convaincre cet investisseur.',
            $title,
            $msgCount
        );

        return [
            'user_type'          => 'startup',
            'coach_summary'      => $coachSummary,
            'hesitation_reasons' => $hesitations,
            'weak_points'        => $weakPoints,
            'suggestions'        => $suggestions,
            'improvement_tips'   => $tips,
            'recommended_message' => $recommended,
            'action_steps'       => $actionSteps,
            'source'             => 'local',
            'fallback_reason'    => $this->humanizeError($errorCode),
        ];
    }

    // ── Sanitize OpenAI response (startup) ───────────────────────────────────

    private function sanitizeStartup(array $r): array
    {
        return [
            'coach_summary'       => (string) ($r['coach_summary']   ?? ''),
            'hesitation_reasons'  => array_slice((array) ($r['hesitation_reasons']  ?? []), 0, 3),
            'weak_points'         => array_slice((array) ($r['weak_points']         ?? []), 0, 3),
            'suggestions'         => array_slice((array) ($r['suggestions']         ?? []), 0, 4),
            'improvement_tips'    => array_slice((array) ($r['improvement_tips']    ?? []), 0, 3),
            'recommended_message' => (string) ($r['recommended_message'] ?? ''),
            'action_steps'        => array_slice((array) ($r['action_steps']        ?? []), 0, 4),
        ];
    }

    // ── Sanitize OpenAI response (investor) ──────────────────────────────────

    private function sanitize(array $r): array
    {
        $sub = $r['sub_scores'] ?? [];
        return [
            'user_type'        => 'investor',
            'score'            => max(0, min(100, (int) ($r['score'] ?? 50))),
            'recommendation'   => $r['recommendation']   ?? 'Négocier',
            'risk_level'       => $r['risk_level']        ?? 'Modéré',
            'sub_scores'       => [
                'rentabilite'         => max(0, min(100, (int) ($sub['rentabilite']         ?? 50))),
                'risque_maitrise'     => max(0, min(100, (int) ($sub['risque_maitrise']     ?? 50))),
                'qualite_negociation' => max(0, min(100, (int) ($sub['qualite_negociation'] ?? 50))),
                'potentiel_projet'    => max(0, min(100, (int) ($sub['potentiel_projet']    ?? 50))),
                'coherence_budget'    => max(0, min(100, (int) ($sub['coherence_budget']    ?? 50))),
            ],
            'resume'           => (string) ($r['resume']        ?? ''),
            'action_plan'      => (array)  ($r['action_plan']   ?? []),
            'badges'           => array_slice((array) ($r['badges']     ?? []), 0, 3),
            'strengths'        => array_slice((array) ($r['strengths']  ?? []), 0, 3),
            'weaknesses'       => array_slice((array) ($r['weaknesses'] ?? []), 0, 3),
            'draft_pro'        => (string) ($r['draft_pro']        ?? ''),
            'draft_diplomatic' => (string) ($r['draft_diplomatic'] ?? ''),
            'draft_direct'     => (string) ($r['draft_direct']     ?? ''),
            'draft_persuasif'  => (string) ($r['draft_persuasif']  ?? ''),
        ];
    }

    // ── Utilities ─────────────────────────────────────────────────────────────

    private function isHotSector(string $s): bool
    {
        foreach (['tech', 'fintech', 'ia', 'ai', 'saas', 'santé', 'health', 'green', 'energie', 'digital', 'cloud', 'biotech', 'edtech'] as $hot) {
            if (str_contains($s, $hot)) return true;
        }
        return false;
    }

    private function hasOfferMessages(array $messages): bool
    {
        foreach ($messages as $m) {
            if ($m->getMessage_type() === 'offer' || $m->getProposed_amount()) return true;
        }
        return false;
    }

    private function humanizeError(string $code): string
    {
        return match ($code) {
            'quota_exceeded'     => 'Quota OpenAI dépassé — analyse heuristique locale',
            'invalid_key'        => 'Clé API invalide — analyse heuristique locale',
            'openai_unavailable' => 'Service OpenAI indisponible — analyse heuristique locale',
            default              => 'Analyse heuristique locale (service IA externe non disponible)',
        };
    }

    // ── Génération de brouillon à la demande ──────────────────────────────────

    /**
     * Génère un brouillon de message de négociation selon le style demandé.
     * Retourne toujours une chaîne (fallback local si OpenAI indisponible).
     *
     * @param string $style  professionnel | diplomatique | direct | persuasif
     */
    public function generateDraft(Negotiation $neg, array $messages, string $style): string
    {
        $style = in_array($style, ['professionnel', 'diplomatique', 'direct', 'persuasif'], true)
            ? $style
            : 'professionnel';

        if (empty($this->openaiApiKey) || str_starts_with($this->openaiApiKey, 'your_')) {
            return $this->localDraftByStyle($neg, $style);
        }

        try {
            return $this->callOpenAiDraft($neg, $messages, $style);
        } catch (\Throwable) {
            return $this->localDraftByStyle($neg, $style);
        }
    }

    private function callOpenAiDraft(Negotiation $neg, array $messages, string $style): string
    {
        $project  = $neg->getProject();
        $proposed = number_format((float) $neg->getProposed_amount(), 0, ',', ' ');
        $budget   = $project ? number_format((float) $project->getRequiredBudget(), 0, ',', ' ') : '?';
        $title    = $project?->getTitle() ?? 'ce projet';
        $secteur  = $project?->getSecteur() ?? 'non précisé';

        $chat = '';
        foreach (array_slice($messages, -8) as $msg) {
            $role   = ($msg->getUser()?->getUserId() === $neg->getInvestor()?->getUserId()) ? 'Investisseur' : 'Startup';
            $amount = $msg->getProposed_amount()
                ? ' [Offre: ' . number_format((float) $msg->getProposed_amount(), 0, ',', ' ') . ' TND]'
                : '';
            $chat .= "{$role}: {$msg->getMessage()}{$amount}\n";
        }

        $styleInstructions = match ($style) {
            'professionnel' => "Rédige un message formel et professionnel, structuré, avec une formule d'introduction et de clôture. Style investisseur institutionnel.",
            'diplomatique'  => "Rédige un message ouvert et bienveillant qui ménage la relation et exprime l'intérêt tout en posant les conditions avec tact. Style partenariat équilibré.",
            'direct'        => "Rédige un message court, factuel, sans fioritures. Maximum 3 phrases. Va droit au but.",
            'persuasif'     => "Rédige un message enthousiaste qui valorise sincèrement le potentiel du projet et pousse vers une conclusion positive. Sois convaincant et engageant, sans être pressant.",
        };

        $system = <<<PROMPT
Tu es un investisseur rédigeant un message dans une négociation d'investissement sur BizHub (plateforme tunisienne).
{$styleInstructions}
Règles : texte en français uniquement, prêt à envoyer directement, sans introduction, sans guillemets, sans balises.
PROMPT;

        $user = "Projet : {$title} (secteur : {$secteur})\nBudget requis : {$budget} TND | Montant proposé : {$proposed} TND\n\nDerniers échanges :\n{$chat}\nRédige le message maintenant.";

        $response = $this->httpClient->request('POST', self::OPENAI_URL, [
            'headers' => ['Authorization' => 'Bearer ' . $this->openaiApiKey, 'Content-Type' => 'application/json'],
            'timeout' => 15,
            'json'    => [
                'model'       => self::MODEL,
                'temperature' => 0.7,
                'max_tokens'  => 350,
                'messages'    => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user',   'content' => $user],
                ],
            ],
        ]);

        $code = $response->getStatusCode();
        if ($code === 429) throw new \RuntimeException('quota_exceeded');
        if ($code === 401 || $code === 403) throw new \RuntimeException('invalid_key');
        if ($code >= 400) throw new \RuntimeException('api_error_' . $code);

        $content = $response->toArray()['choices'][0]['message']['content'] ?? null;
        if (!$content || trim($content) === '') throw new \RuntimeException('empty_response');

        return trim($content);
    }

    private function localDraftByStyle(Negotiation $neg, string $style): string
    {
        $project  = $neg->getProject();
        $proposed = (float) $neg->getProposed_amount();
        $budget   = $project ? (float) $project->getRequiredBudget() : 0;
        $title    = $project?->getTitle() ?? 'votre projet';
        $ratio    = $budget > 0 ? ($proposed / $budget) : 0;

        // Déduit la recommandation locale pour choisir le bon brouillon
        $rec = $ratio >= 0.7 ? 'Investir' : ($ratio >= 0.3 ? 'Négocier' : 'Décliner');

        $drafts = $this->buildDrafts($rec, $title, $proposed, $budget);

        return $drafts[$style] ?? $drafts['pro'];
    }
}
