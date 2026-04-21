<?php

namespace App\Twig;

use App\Service\Investissement\MoneyHelper;
use Money\Money;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Filtres et fonctions Twig pour l'affichage des montants monétaires.
 *
 * Usage dans les templates :
 *   {{ 1500|money_fmt }}              → "1 500,000 TND"
 *   {{ 1500|money_fmt('EUR') }}       → "1 500,00 EUR"
 *   {{ moneyObject|money_obj_fmt }}   → formatte un objet Money directement
 */
class MoneyExtension extends AbstractExtension
{
    public function __construct(private MoneyHelper $moneyHelper) {}

    public function getFilters(): array
    {
        return [
            // Filtre float → chaîne formatée :  {{ inv.amount|money_fmt }}
            new TwigFilter('money_fmt', $this->formatFloat(...)),

            // Filtre objet Money → chaîne :     {{ moneyObject|money_obj_fmt }}
            new TwigFilter('money_obj_fmt', $this->formatMoney(...)),
        ];
    }

    public function getFunctions(): array
    {
        return [
            // Fonction : {{ money_of(1500, 'TND') }} → retourne un objet Money
            new TwigFunction('money_of', $this->moneyHelper->of(...)),
        ];
    }

    public function formatFloat(float|null $amount, string $currency = 'TND', string $locale = 'fr_TN'): string
    {
        if ($amount === null) {
            return '—';
        }
        return $this->moneyHelper->formatFloat($amount, $currency, $locale);
    }

    public function formatMoney(Money|null $money, string $locale = 'fr_TN'): string
    {
        if ($money === null) {
            return '—';
        }
        return $this->moneyHelper->format($money, $locale);
    }
}
