<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\UsersAvis\User;
use App\Service\FacePlusPlus\FaceRecognitionService;
use App\Service\FacePlusPlus\ImagePreprocessingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/verify/face')]
class FaceVerificationController extends AbstractController
{
    private const CONFIDENCE_THRESHOLD = 70.0;

    public function __construct(
        private readonly FaceRecognitionService $faceRecognitionService,
        private readonly ImagePreprocessingService $imagePreprocessingService,
    ) {
    }

    #[Route('', name: 'app_face_verify', methods: ['GET', 'POST'])]
    public function verify(Request $request): RedirectResponse|Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($user->getFace_token() === null) {
            $this->addFlash('warning', 'Face authentication is not set up. Please enroll first.');
            return $this->redirectToRoute('app_face_enroll');
        }

        if ($request->isMethod('POST')) {
            $base64Image = (string) $request->request->get('image');
            if ($base64Image === '') {
                $this->addFlash('error', 'Please capture a photo first.');
                return $this->redirectToRoute('app_face_verify');
            }

            if (str_starts_with($base64Image, 'data:')) {
                $base64Image = preg_replace('#^data:image/[^;]+;base64,#', '', $base64Image) ?: '';
            }

            try {
                $processedBase64 = $this->imagePreprocessingService->toGrayscaleBase64($base64Image);
            } catch (\InvalidArgumentException|\RuntimeException $e) {
                $this->addFlash('error', 'Failed to process image: ' . $e->getMessage());
                return $this->redirectToRoute('app_face_verify');
            }

            $faceDir = $this->getParameter('kernel.project_dir') . '/var/face_images';
            $storedImagePath = sprintf('%s/%d.jpg', $faceDir, $user->getUserId());

            if (!file_exists($storedImagePath)) {
                $this->addFlash('error', 'Stored face image not found. Please re-enroll.');
                return $this->redirectToRoute('app_face_enroll');
            }

            $storedBase64 = base64_encode(file_get_contents($storedImagePath));

            try {
                $confidence = $this->faceRecognitionService->compareFaces($storedBase64, $processedBase64);
            } catch (\App\Exception\FacePlusPlus\FaceComparisonException $e) {
                $this->addFlash('error', 'Face verification failed: ' . $e->getMessage());
                return $this->redirectToRoute('app_face_verify');
            }

            if ($confidence >= self::CONFIDENCE_THRESHOLD) {
                $request->getSession()->set('face_verified', true);
                $this->addFlash('success', 'Face verification successful.');

                return $this->redirectToRoute('app_user_dashboard');
            }

            $this->addFlash('error', sprintf('Face verification failed. Confidence: %.1f%% (threshold: %.1f%%)', $confidence, self::CONFIDENCE_THRESHOLD));

            return $this->redirectToRoute('app_face_verify');
        }

        return $this->render('face/verify.html.twig');
    }
}