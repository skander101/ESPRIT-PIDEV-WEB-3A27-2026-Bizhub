<?php

namespace App\Tests\Controller\UsersAvis;

use App\Controller\UsersAvis\UserController;
use App\Entity\UsersAvis\User;
use App\Repository\UsersAvis\UserRepository;
use App\Service\Ai\CloudflareAiService;
use App\Service\Auth\AuthMailerService;
use App\Service\Auth\SecureTokenService;
use App\Service\Auth\UserAuthStateService;
use App\Service\FacePlusPlus\FaceRecognitionService;
use App\Service\FacePlusPlus\ImagePreprocessingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validation;

class UserAiAvatarControllerTest extends TestCase
{
    private ?string $tempProjectDir = null;
    private ?string $createdFilePath = null;

    protected function tearDown(): void
    {
        if ($this->createdFilePath !== null && is_file($this->createdFilePath)) {
            @unlink($this->createdFilePath);
        }

        if ($this->tempProjectDir !== null && is_dir($this->tempProjectDir)) {
            @rmdir($this->tempProjectDir.'/public/assets/images/avatars');
            @rmdir($this->tempProjectDir.'/public/assets/images');
            @rmdir($this->tempProjectDir.'/public/assets');
            @rmdir($this->tempProjectDir.'/public');
            @rmdir($this->tempProjectDir);
        }

        parent::tearDown();
    }

    public function testGenerateAiAvatarFlow(): void
    {
        $this->tempProjectDir = sys_get_temp_dir().'/bizhub-ai-test-'.uniqid('', true);
        mkdir($this->tempProjectDir.'/public/assets/images/avatars', 0775, true);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $user = (new User())
            ->setEmail('ai-test@example.com')
            ->setPassword_hash('dummy')
            ->setFull_name('AI Test User')
            ->setUser_type('startup')
            ->setIs_active(true);

        $controller = new class(
            $entityManager,
            $this->createMock(UserRepository::class),
            $this->createMock(UserPasswordHasherInterface::class),
            $this->createMock(SluggerInterface::class),
            $this->createMock(SecureTokenService::class),
            $this->createMock(UserAuthStateService::class),
            $this->createMock(AuthMailerService::class),
            $this->createMock(FaceRecognitionService::class),
            $this->createMock(ImagePreprocessingService::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(TokenStorageInterface::class),
            $this->createMock(TotpAuthenticator::class),
            $this->createMock(UserAuthenticatorInterface::class),
            $this->tempProjectDir,
            $user,
        ) extends UserController {
            public function __construct(
                EntityManagerInterface $entityManager,
                UserRepository $userRepository,
                UserPasswordHasherInterface $passwordHasher,
                SluggerInterface $slugger,
                SecureTokenService $tokenService,
                UserAuthStateService $userAuthStateService,
                AuthMailerService $authMailerService,
                FaceRecognitionService $faceRecognitionService,
                ImagePreprocessingService $imagePreprocessingService,
                LoggerInterface $logger,
                TokenStorageInterface $tokenStorage,
                TotpAuthenticator $totpAuthenticator,
                UserAuthenticatorInterface $userAuthenticator,
                private readonly string $projectDir,
                private readonly User $fakeUser,
            ) {
                parent::__construct(
                    $entityManager,
                    $userRepository,
                    $passwordHasher,
                    $slugger,
                    $tokenService,
                    $userAuthStateService,
                    $authMailerService,
                    $faceRecognitionService,
                    $imagePreprocessingService,
                    $logger,
                    $tokenStorage,
                    $totpAuthenticator,
                    $userAuthenticator,
                );
            }

            public function getUser(): ?UserInterface
            {
                return $this->fakeUser;
            }

            public function getParameter(string $name): array|bool|string|int|float|\UnitEnum|null
            {
                if ($name === 'kernel.project_dir') {
                    return $this->projectDir;
                }

                return null;
            }

            public function json(mixed $data, int $status = 200, array $headers = [], array $context = []): JsonResponse
            {
                return new JsonResponse($data, $status, $headers);
            }
        };

        $csrfManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfManager->method('isTokenValid')->willReturn(true);

        $cloudflare = $this->createMock(CloudflareAiService::class);
        $cloudflare->method('isConfigured')->willReturn(true);
        $cloudflare->method('generateImage')->willReturn([
            'bytes' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgYf5M/0AAAAASUVORK5CYII=', true),
            'mimeType' => 'image/png',
        ]);

        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $request = new Request([], [
            'prompt' => 'Professional headshot portrait, friendly, business profile photo, neutral background',
            '_token' => 'valid-token',
        ]);

        $response = $controller->generateAiAvatar($request, $validator, $csrfManager, $cloudflare);
        self::assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true);
        self::assertIsArray($payload);
        self::assertTrue($payload['success'] ?? false);
        self::assertStringStartsWith('/assets/images/avatars/ai-avatar-', (string) $payload['avatarUrl']);

        $this->createdFilePath = $this->tempProjectDir.'/public'.$payload['avatarUrl'];
        self::assertFileExists($this->createdFilePath);
    }
}
