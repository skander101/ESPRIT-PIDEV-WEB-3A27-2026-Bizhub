<?php

namespace App\Service\Investissement;

use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Money;
use Money\Parser\DecimalMoneyParser;

/**
 * Centralise toutes les opérations sur les montants monétaires.
 *
 * Prérequis : composer require moneyphp/money
 *
 * Pourquoi ce service existe :
 *   Les floats sont imprécis en binaire (ex: 0.1 + 0.2 = 0.30000000000000004).
 *   moneyphp/money stocke les montants en entiers (millimes) et garantit
 *   une précision absolue pour tous les calculs financiers.
 *
 * Convention TND : 1 TND = 1 000 millimes  (3 décimales ISO 4217)
 * Convention EUR : 1 EUR = 100 centimes     (2 décimales ISO 4217)
 * Convention USD : 1 USD = 100 cents        (2 décimales ISO 4217)
 */
class MoneyHelper
{
    public const DEFAULT_CURRENCY = 'TND';

    private ISOCurrencies $currencies;
    private DecimalMoneyParser $parser;
    private DecimalMoneyFormatter $formatter;

    public function __construct()
    {
        $this->currencies = new ISOCurrencies();
        $this->parser     = new DecimalMoneyParser($this->currencies);
        $this->formatter  = new DecimalMoneyFormatter($this->currencies);
    }

    // ── Création ─────────────────────────────────────────────────────────────

    /**
     * Crée un objet Money depuis un float ou une chaîne.
     *
     * Exemples :
     *   $helper->of(1500)        → Money { 1 500 000 millimes, TND }
     *   $helper->of(1500, 'EUR') → Money { 150 000 centimes, EUR }
     */
    public function of(float|string $amount, string $currency = self::DEFAULT_CURRENCY): Money
    {
        return $this->parser->parse(
            number_format((float) $amount, 6, '.', ''),
            new Currency(strtoupper($currency))
        );
    }

    /**
     * Retourne un montant zéro dans la devise donnée.
     */
    public function zero(string $currency = self::DEFAULT_CURRENCY): Money
    {
        return $this->of(0, $currency);
    }

    // ── Conversion ────────────────────────────────────────────────────────────

    /**
     * Convertit un objet Money en float (pour stockage DB ou affichage brut).
     *
     * Exemple : Money { 1 500 000 millimes, TND } → 1500.0
     */
    public function toFloat(Money $money): float
    {
        return (float) $this->formatter->format($money);
    }

    /**
     * Retourne la représentation décimale exacte.
     *
     * Exemple : Money { 1 500 000 millimes, TND } → "1500.000"
     */
    public function toDecimalString(Money $money): string
    {
        return $this->formatter->format($money);
    }

    // ── Calculs ───────────────────────────────────────────────────────────────

    /**
     * Additionne un tableau d'objets Money (même devise).
     *
     * Exemple :
     *   $total = $helper->sum([$money1, $money2, $money3]);
     */
    public function sum(array $moneys, string $currency = self::DEFAULT_CURRENCY): Money
    {
        $total = $this->zero($currency);
        foreach ($moneys as $m) {
            $total = $total->add($m);
        }
        return $total;
    }

    /**
     * Calcule le total investi depuis un tableau d'entités Investment.
     *
     * Exemple :
     *   $total = $helper->sumInvestments($investissements); // retourne Money
     *   echo $helper->toFloat($total); // ex: 47500.0
     */
    public function sumInvestments(array $investments, string $currency = self::DEFAULT_CURRENCY): Money
    {
        $total = $this->zero($currency);
        foreach ($investments as $inv) {
            $total = $total->add($this->of($inv->getAmount() ?? 0, $currency));
        }
        return $total;
    }

    /**
     * Calcule le pourcentage de financement atteint (0 → 100).
     *
     * Exemple :
     *   $pct = $helper->fundingPercentage(
     *       $helper->of(47500),     // collecté
     *       $helper->of(100000)     // objectif
     *   ); // → 47.5
     */
    public function fundingPercentage(Money $collected, Money $required): float
    {
        if ($required->isZero()) {
            return 0.0;
        }
        return min(100.0, round(
            ($this->toFloat($collected) / $this->toFloat($required)) * 100,
            1
        ));
    }

    // ── Formatage ─────────────────────────────────────────────────────────────

    /**
     * Formate un objet Money pour l'affichage (ex : "1 500,000 TND").
     *
     * @param string $locale  Locale PHP/ICU (fr_TN, fr_FR, en_US…)
     */
    public function format(Money $money, string $locale = 'fr_TN'): string
    {
        $code   = $money->getCurrency()->getCode();
        $amount = $this->toFloat($money);

        if (extension_loaded('intl')) {
            $fmt = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
            $fmt->setAttribute(\NumberFormatter::FRACTION_DIGITS, $this->subunitDecimals($code));
            return $fmt->format($amount) . ' ' . $code;
        }

        // Fallback sans ext-intl
        return number_format($amount, $this->subunitDecimals($code), ',', ' ') . ' ' . $code;
    }

    /**
     * Raccourci : formate directement un float sans créer un Money au préalable.
     *
     * Exemple :
     *   $helper->formatFloat(1500.0, 'TND') → "1 500,000 TND"
     */
    public function formatFloat(float $amount, string $currency = self::DEFAULT_CURRENCY, string $locale = 'fr_TN'): string
    {
        return $this->format($this->of($amount, $currency), $locale);
    }

    // ── Comparaisons ──────────────────────────────────────────────────────────

    /**
     * Vérifie si un projet est entièrement financé.
     */
    public function isFullyFunded(Money $collected, Money $required): bool
    {
        return $collected->greaterThanOrEqual($required);
    }

    // ── Helpers privés ────────────────────────────────────────────────────────

    /**
     * Nombre de décimales significatives par devise (ISO 4217).
     */
    private function subunitDecimals(string $currencyCode): int
    {
        return match (strtoupper($currencyCode)) {
            'TND', 'KWD', 'BHD', 'OMR' => 3,
            'JPY', 'VND', 'IDR'         => 0,
            default                     => 2,
        };
    }
}
