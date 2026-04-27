<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\UsersAvis\User;
use App\Service\FacePlusPlus\FaceRecognitionService;
use App\Service\FacePlusPlus\ImagePreprocessingService;
use App\Service\Auth\UserAuthStateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/profile/face')]
class FaceEnrollmentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FaceRecognitionService $faceRecognitionService,
        private readonly ImagePreprocessingService $imagePreprocessingService,
        private readonly UserAuthStateService $userAuthStateService,
    ) {
    }

    #[Route('/enroll', name: 'app_face_enroll', methods: ['GET'])]
    public function enroll(Request $request): RedirectResponse|Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->userAuthStateService->isVerified($user)) {
            $this->addFlash('warning', 'Please verify your email before enrolling face authentication.');
            return $this->redirectToRoute('app_user_dashboard');
        }

        return $this->render('face/enroll.html.twig', [
            'enrolled' => $user->getFace_token() !== null,
        ]);
    }

    #[Route('/enroll', name: 'app_face_enroll_submit', methods: ['POST'])]
    public function enrollSubmit(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $base64Image = (string) $request->request->get('image');
        if ($base64Image === '') {
            $this->addFlash('error', 'Please capture a photo first.');
            return $this->redirectToRoute('app_face_enroll');
        }

        if (str_starts_with($base64Image, 'data:')) {
            $base64Image = preg_replace('#^data:image/[^;]+;base64,#', '', $base64Image) ?: '';
        }

        try {
            $processedBase64 = $this->imagePreprocessingService->toGrayscaleBase64($base64Image);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $this->addFlash('error', 'Failed to process image: ' . $e->getMessage());
            return $this->redirectToRoute('app_face_enroll');
        }

        try {
            $faceData = $this->faceRecognitionService->detectFace($processedBase64);
        } catch (\App\Exception\FacePlusPlus\FaceDetectionException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_face_enroll');
        }

        $faceToken = $faceData['face_token'] ?? null;
        if ($faceToken === null) {
            $this->addFlash('error', 'Failed to extract face token from the image.');
            return $this->redirectToRoute('app_face_enroll');
        }

        $user->setFace_token($faceToken);
        $user->setFaceEnrolledAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $faceDir = $this->getParameter('kernel.project_dir') . '/var/face_images';
        if (!is_dir($faceDir)) {
            mkdir($faceDir, 0755, true);
        }
        file_put_contents(sprintf('%s/%d.jpg', $faceDir, $user->getUserId()), base64_decode($processedBase64));

        $this->addFlash('success', 'Face enrollment successful.');

        return $this->redirectToRoute('app_user_dashboard');
    }

    #[Route('/delete', name: 'app_face_delete', methods: ['POST'])]
    public function delete(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('delete_face', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_user_dashboard');
        }

        $user->setFace_token(null);
        $user->setFaceEnrolledAt(null);
        $this->entityManager->flush();

        $faceFile = $this->getParameter('kernel.project_dir') . '/var/face_images/' . $user->getUserId() . '.jpg';
        if (file_exists($faceFile)) {
            unlink($faceFile);
        }

        $this->addFlash('success', 'Face enrollment has been removed.');

        return $this->redirectToRoute('app_user_dashboard');
    }
}