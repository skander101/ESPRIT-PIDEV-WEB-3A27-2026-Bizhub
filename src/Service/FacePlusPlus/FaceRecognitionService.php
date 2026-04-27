<?php

declare(strict_types=1);

namespace App\Service\FacePlusPlus;

use App\Exception\FacePlusPlus\FaceDetectionException;
use App\Exception\FacePlusPlus\FaceComparisonException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FaceRecognitionService
{
    private const DETECT_URL = 'https://api-us.faceplusplus.com/facepp/v3/detect';
    private const COMPARE_URL = 'https://api-us.faceplusplus.com/facepp/v3/compare';

    public function __construct(
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'FACE_API_KEY')] private readonly string $faceApiKey,
        #[Autowire(env: 'FACE_API_SECRET')] private readonly string $faceApiSecret,
    ) {
    }

    private function getHttpClient(): HttpClientInterface
    {
        return new NativeHttpClient();
    }

    public function detectFace(string $base64Image): array
    {
        try {
            $response = $this->getHttpClient()->request('POST', self::DETECT_URL, [
                'timeout' => 10.0,
                'body' => [
                    'api_key' => $this->faceApiKey,
                    'api_secret' => $this->faceApiSecret,
                    'image_base64' => $base64Image,
                ],
            ]);

            $content = $response->toArray();
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Face++ API transport error during detection', [
                'exception' => $e->getMessage(),
            ]);
            throw new FaceDetectionException('Failed to communicate with Face++ API: ' . $e->getMessage());
        } catch (\UnexpectedValueException $e) {
            $this->logger->error('Face++ API invalid response during detection', [
                'exception' => $e->getMessage(),
            ]);
            throw new FaceDetectionException('Invalid response from Face++ API');
        }

        $faceNum = $content['face_num'] ?? 0;
        if ($faceNum === 0) {
            throw new FaceDetectionException('No face detected in the image. Please upload a clear photo of your face.');
        }
        if ($faceNum > 1) {
            throw new FaceDetectionException('Multiple faces detected. Please upload an image with only one face.');
        }

        return $content['faces'][0] ?? [];
    }

    public function compareFaces(string $base64Image1, string $base64Image2): float
    {
        try {
            $response = $this->getHttpClient()->request('POST', self::COMPARE_URL, [
                'timeout' => 10.0,
                'body' => [
                    'api_key' => $this->faceApiKey,
                    'api_secret' => $this->faceApiSecret,
                    'image_base64_1' => $base64Image1,
                    'image_base64_2' => $base64Image2,
                ],
            ]);

            $content = $response->toArray();
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Face++ API transport error during comparison', [
                'exception' => $e->getMessage(),
            ]);
            throw new FaceComparisonException('Failed to communicate with Face++ API: ' . $e->getMessage());
        } catch (\UnexpectedValueException $e) {
            $this->logger->error('Face++ API invalid response during comparison', [
                'exception' => $e->getMessage(),
            ]);
            throw new FaceComparisonException('Invalid response from Face++ API');
        }

        if (!isset($content['confidence'])) {
            throw new FaceComparisonException('Missing confidence score in API response');
        }

        return (float) $content['confidence'];
    }
}