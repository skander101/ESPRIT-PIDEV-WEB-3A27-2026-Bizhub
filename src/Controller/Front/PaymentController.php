<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Elearning\Participation;
use App\Entity\UsersAvis\User;
use App\Repository\Elearning\ParticipationRepository;
use App\Service\Elearning\FakeCardPaymentValidator;
use App\Service\Elearning\ParticipationCertificatePdfService;
use App\Service\Elearning\ParticipationConfirmationMailService;
use App\Service\Elearning\PromoCodeService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/payment')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class PaymentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ParticipationRepository $participationRepository,
        private readonly FakeCardPaymentValidator $fakeCardPaymentValidator,
        private readonly ParticipationCertificatePdfService $certificatePdfService,
        private readonly ParticipationConfirmationMailService $confirmationMailService,
        private readonly PromoCodeService $promoCodeService,
        private readonly LoggerInterface $logger,
        private readonly string $projectDir,
    ) {
    }

    #[Route('/checkout/{id}', name: 'app_payment_checkout', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function checkout(int $id): Response
    {
        $participation = $this->loadUserParticipationAwaitingPayment($id);

        return $this->render('front/payment/checkout.html.twig', $this->buildCheckoutViewVars($participation));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCheckoutViewVars(Participation $participation): array
    {
        $formation = $participation->getFormation();
        $ttc = (float) ($participation->getAmount() ?? 0.0);
        $vatRate = (float) $this->getParameter('app.elearning.vat_rate');
        $ht = $vatRate > 0 ? round($ttc / (1 + $vatRate), 3) : $ttc;
        $tva = round($ttc - $ht, 3);
        $startYear = (int) (new \DateTimeImmutable())->format('Y');

        return [
            'participation' => $participation,
            'formation' => $formation,
            'ttc' => $ttc,
            'ht' => $ht,
            'tva' => $tva,
            'vat_rate' => $vatRate,
            'vat_rate_percent' => (int) round($vatRate * 100),
            'exp_years' => range($startYear, $startYear + 12),
        ];
    }

    #[Route('/validate-promo/{id}', name: 'app_payment_validate_promo', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function validatePromo(Request $request, int $id): JsonResponse
    {
        $participation = $this->loadUserParticipationAwaitingPayment($id);
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['ok' => false, 'message' => 'Non authentifié.'], 401);
        }

        if (!$this->isCsrfTokenValid('payment_validate_promo_' . $id, $request->request->getString('_token'))) {
            return new JsonResponse(['ok' => false, 'message' => 'Session invalide. Rechargez la page.'], 403);
        }

        $code = $request->request->getString('promo_code');
        $baseTtc = (float) ($participation->getAmount() ?? 0.0);
        $eval = $this->promoCodeService->evaluatePromoForCheckout($user, $code, $baseTtc);
        $vatRate = (float) $this->getParameter('app.elearning.vat_rate');

        if (!$eval['ok']) {
            return new JsonResponse([
                'ok' => false,
                'message' => $eval['message'] ?? 'Code invalide.',
            ]);
        }

        $charged = (float) ($eval['amount_ttc'] ?? $baseTtc);
        $ht = $vatRate > 0 ? round($charged / (1 + $vatRate), 3) : $charged;
        $tva = round($charged - $ht, 3);
        $discountPercent = (int) round(($eval['discount_rate'] ?? 0.0) * 100);

        return new JsonResponse([
            'ok' => true,
            'message' => $eval['message'],
            'discount_percent' => $discountPercent,
            'discount_rate' => $eval['discount_rate'] ?? 0.0,
            'base_ttc' => $baseTtc,
            'amount_ttc' => $charged,
            'ht' => $ht,
            'tva' => $tva,
            'database_promo' => $eval['promo'] !== null,
        ]);
    }

    #[Route('/process/{id}', name: 'app_payment_process', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function process(Request $request, int $id): Response
    {
        $participation = $this->loadUserParticipationAwaitingPayment($id);
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('payment_process_' . $id, $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Session de paiement invalide. Réessayez.');

            return $this->redirectToRoute('app_payment_checkout', ['id' => $id]);
        }

        $cardNumber = $request->request->getString('card_number');
        $holder = $request->request->getString('card_holder');
        $expMonth = (int) $request->request->get('exp_month');
        $expYear = (int) $request->request->get('exp_year');
        $cvv = $request->request->getString('cvv');
        $promoInput = $request->request->getString('promo_code');

        $base = (float) ($participation->getAmount() ?? 0.0);
        $eval = $this->promoCodeService->evaluatePromoForCheckout($user, $promoInput, $base);
        if (!$eval['ok']) {
            $this->addFlash('danger', $eval['message'] ?? 'Code promo invalide.');

            return $this->redirectToRoute('app_payment_checkout', ['id' => $id]);
        }

        $charged = (float) ($eval['amount_ttc'] ?? $base);
        $appliedDbPromo = $eval['promo'];

        $validation = $this->fakeCardPaymentValidator->validatePaymentPayload($cardNumber, $holder, $expMonth, $expYear, $cvv);
        if (!$validation['ok']) {
            foreach ($validation['errors'] as $err) {
                $this->addFlash('danger', $err);
            }

            return $this->redirectToRoute('app_payment_checkout', ['id' => $id]);
        }

        $txn = $this->fakeCardPaymentValidator->generateTransactionId();
        $now = new \DateTimeImmutable();

        $participation->setAmount((string) $charged);
        $participation->setTransactionId($txn);
        $participation->setPaymentStatus('PAID');
        $participation->setPaymentProvider('FAKE_GATEWAY');
        $participation->setPaymentRef($txn);
        $participation->setPaidAt($now);
        $participation->setStatus(Participation::STATUS_PAID);

        if ($appliedDbPromo !== null) {
            $this->promoCodeService->markUsed($appliedDbPromo, $now);
        }

        $giftPromo = $this->promoCodeService->createRewardAfterPayment($user, $participation);

        $verifyUrl = $this->generateUrl('app_front_certificate_verify', [
            'id' => $participation->getId_candidature(),
            'sig' => $this->buildVerificationSignature($participation),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $relativePdfPath = $this->certificatePdfService->generateAndSave($participation, $verifyUrl);
        $participation->setCertificatePath($relativePdfPath);

        $this->entityManager->flush();

        $absolutePdf = $this->projectDir . '/public' . $relativePdfPath;
        try {
            $this->confirmationMailService->send($participation, $absolutePdf, $giftPromo);
        } catch (\Throwable $e) {
            $this->logger->error('Participation confirmation mail failed: ' . $e->getMessage(), ['exception' => $e]);
            $this->addFlash('warning', 'Paiement enregistré, mais l\'envoi de l\'email a échoué. Contactez le support si besoin.');
        }

        $request->getSession()->set('payment_success_' . $id, (string) time());

        return $this->redirectToRoute('app_payment_success', ['id' => $id], Response::HTTP_SEE_OTHER);
    }

    #[Route('/success/{id}', name: 'app_payment_success', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function success(Request $request, int $id): Response
    {
        $participation = $this->loadUserParticipation($id);
        if (!$participation->isPaidEnrollment()) {
            return $this->redirectToRoute('app_payment_checkout', ['id' => $id]);
        }

        $flag = $request->getSession()->get('payment_success_' . $id);
        if ($flag === null) {
            return $this->redirectToRoute('app_front_formations_index');
        }
        $request->getSession()->remove('payment_success_' . $id);

        return $this->render('front/payment/success.html.twig', [
            'participation' => $participation,
            'formation' => $participation->getFormation(),
        ]);
    }

    #[Route('/cancel/{id}', name: 'app_payment_cancel', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function cancel(int $id): Response
    {
        $participation = $this->loadUserParticipation($id);
        if (!$participation->isAwaitingPayment()) {
            return $this->redirectToRoute('app_front_formations_index');
        }

        $formation = $participation->getFormation();
        $this->entityManager->remove($participation);
        $this->entityManager->flush();
        $this->addFlash('info', 'Paiement annulé. Vous pouvez vous réinscrire à la formation.');

        return $this->render('front/payment/cancel.html.twig', [
            'formation' => $formation,
        ]);
    }

    private function loadUserParticipation(int $id): Participation
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $p = $this->participationRepository->find($id);
        if (!$p instanceof Participation || $p->getUser()?->getUser_id() !== $user->getUser_id()) {
            throw $this->createNotFoundException();
        }

        return $p;
    }

    private function loadUserParticipationAwaitingPayment(int $id): Participation
    {
        $p = $this->loadUserParticipation($id);
        if (!$p->isAwaitingPayment()) {
            throw $this->createAccessDeniedException('Cette inscription n\'est pas en attente de paiement.');
        }

        return $p;
    }

    private function buildVerificationSignature(Participation $participation): string
    {
        $secret = (string) $this->getParameter('kernel.secret');

        return substr(hash_hmac('sha256', (string) $participation->getId_candidature(), $secret), 0, 48);
    }
}
