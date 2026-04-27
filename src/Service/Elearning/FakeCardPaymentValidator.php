<?php

declare(strict_types=1);

namespace App\Service\Elearning;

/**
 * Simulation de paiement : format uniquement (13–19 chiffres), sans Luhn,
 * pour permettre les numéros de démo. Une vraie passerelle (Stripe, etc.)
 * effectuera la validation Luhn et bancaire.
 */
final class FakeCardPaymentValidator
{
    public const PROMO_BIZHUB10 = 'BIZHUB10';

    /**
     * @return array{ok: bool, errors: list<string>, discount_rate: float}
     */
    public function validatePaymentPayload(
        string $cardNumberDigits,
        string $holder,
        int $expMonth,
        int $expYear,
        string $cvv,
    ): array {
        $errors = [];
        $digits = preg_replace('/\D+/', '', $cardNumberDigits) ?? '';

        if (strlen($digits) < 13 || strlen($digits) > 19) {
            $errors[] = 'Numéro de carte : entre 13 et 19 chiffres.';
        }

        $holderTrim = trim($holder);
        if ($holderTrim === '' || !preg_match('/^[\p{L}\s\-\']+$/u', $holderTrim)) {
            $errors[] = 'Nom du titulaire : lettres uniquement.';
        }

        $now = new \DateTimeImmutable('first day of this month midnight');
        $exp = (new \DateTimeImmutable())->setDate($expYear, $expMonth, 1);
        if ($exp < $now) {
            $errors[] = 'La carte est expirée.';
        }

        $cvvDigits = preg_replace('/\D+/', '', $cvv) ?? '';
        if (strlen($cvvDigits) < 3 || strlen($cvvDigits) > 4) {
            $errors[] = 'Cryptogramme : 3 ou 4 chiffres.';
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'discount_rate' => 0.0,
        ];
    }

    public function promoDiscountRate(?string $promoCode): float
    {
        if ($promoCode === null) {
            return 0.0;
        }
        $c = strtoupper(trim($promoCode));

        return $c === self::PROMO_BIZHUB10 ? 0.10 : 0.0;
    }

    public function generateTransactionId(): string
    {
        return 'BH-' . strtoupper(bin2hex(random_bytes(10)));
    }
}
