<?php

declare(strict_types=1);

namespace App\Service\Elearning;

use App\Entity\Elearning\Participation;
use App\Entity\Elearning\PromoCode;
use App\Entity\UsersAvis\User;
use App\Repository\Elearning\PromoCodeRepository;
use Doctrine\ORM\EntityManagerInterface;

final class PromoCodeService
{
    private const CODE_PREFIX = 'NEXT';

    private const CODE_RANDOM_LEN = 8;

    private const VALIDITY_DAYS = 30;

    public function __construct(
        private readonly PromoCodeRepository $promoCodeRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly FakeCardPaymentValidator $fakeCardPaymentValidator,
    ) {
    }

    /**
     * Creates a unique reward code after a successful paid participation.
     */
    public function createRewardAfterPayment(User $user, Participation $sourceParticipation): PromoCode
    {
        $now = new \DateTimeImmutable();
        $expires = $now->modify('+' . self::VALIDITY_DAYS . ' days');
        $percent = random_int(35, 75);

        $promo = (new PromoCode())
            ->setUser($user)
            ->setCode($this->generateUniqueCode($percent))
            ->setDiscountPercent($percent)
            ->setIsUsed(false)
            ->setIsActive(true)
            ->setCreatedAt($now)
            ->setExpiresAt($expires)
            ->setParticipationSource($sourceParticipation);

        $this->entityManager->persist($promo);

        return $promo;
    }

    public function markUsed(PromoCode $promo, \DateTimeImmutable $at): void
    {
        $promo->markUsed($at);
    }

    /**
     * @return array{
     *   ok: bool,
     *   message: string|null,
     *   promo: PromoCode|null,
     *   discount_rate: float,
     *   amount_ttc: float|null
     * }
     */
    public function evaluatePromoForCheckout(User $user, string $rawCode, float $baseTtc): array
    {
        $code = strtoupper(trim($rawCode));
        if ($code === '') {
            return [
                'ok' => true,
                'message' => null,
                'promo' => null,
                'discount_rate' => 0.0,
                'amount_ttc' => $baseTtc,
            ];
        }

        $promo = $this->promoCodeRepository->findOneByCode($code);
        if ($promo === null) {
            $legacyRate = $this->fakeCardPaymentValidator->promoDiscountRate($rawCode);
            if ($legacyRate > 0.0) {
                $chargedLegacy = round($baseTtc * (1 - $legacyRate), 2);

                return [
                    'ok' => true,
                    'message' => sprintf('Code démo %s : −%d%%.', FakeCardPaymentValidator::PROMO_BIZHUB10, (int) round($legacyRate * 100)),
                    'promo' => null,
                    'discount_rate' => $legacyRate,
                    'amount_ttc' => $chargedLegacy,
                ];
            }

            return [
                'ok' => false,
                'message' => 'Code promo inconnu ou invalide.',
                'promo' => null,
                'discount_rate' => 0.0,
                'amount_ttc' => null,
            ];
        }

        $now = new \DateTimeImmutable();
        if ($promo->getUser()?->getUserId() !== $user->getUserId()) {
            return [
                'ok' => false,
                'message' => 'Ce code promo est lié à un autre compte.',
                'promo' => null,
                'discount_rate' => 0.0,
                'amount_ttc' => null,
            ];
        }

        if (!$promo->isUsableNow($now)) {
            return [
                'ok' => false,
                'message' => 'Ce code est expiré, désactivé ou déjà utilisé.',
                'promo' => null,
                'discount_rate' => 0.0,
                'amount_ttc' => null,
            ];
        }

        $rate = min(0.95, max(0.0, $promo->getDiscountPercent() / 100.0));
        $charged = round($baseTtc * (1 - $rate), 2);

        return [
            'ok' => true,
            'message' => sprintf('Réduction de %d%% appliquée.', $promo->getDiscountPercent()),
            'promo' => $promo,
            'discount_rate' => $rate,
            'amount_ttc' => $charged,
        ];
    }

    private function generateUniqueCode(int $percent): string
    {
        $pct = max(0, min(99, $percent));
        for ($i = 0; $i < 40; ++$i) {
            $suffix = $this->randomAlphanumeric(self::CODE_RANDOM_LEN);
            $candidate = self::CODE_PREFIX . sprintf('%02d', $pct) . $suffix;
            if (strlen($candidate) > 32) {
                $candidate = substr($candidate, 0, 32);
            }
            if (!$this->promoCodeRepository->existsByCode($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Impossible de générer un code promo unique.');
    }

    private function randomAlphanumeric(int $length): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = '';
        $max = strlen($alphabet) - 1;
        for ($i = 0; $i < $length; ++$i) {
            $out .= $alphabet[random_int(0, $max)];
        }

        return $out;
    }
}
