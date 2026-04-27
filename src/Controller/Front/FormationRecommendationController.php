<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Elearning\Formation;
use App\Entity\Elearning\FormationRecommendationEvent;
use App\Entity\UsersAvis\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/front/formations')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class FormationRecommendationController extends AbstractController
{
    private const ALLOWED_SECTIONS = [
        FormationRecommendationEvent::SECTION_PERSONALIZED,
        FormationRecommendationEvent::SECTION_TRENDING,
        FormationRecommendationEvent::SECTION_POPULAR,
        FormationRecommendationEvent::SECTION_NEW,
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/recommendations/track', name: 'app_front_formation_recommendations_track', methods: ['POST'])]
    public function track(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['ok' => false], 401);
        }

        $payload = [];
        $ct = (string) $request->headers->get('Content-Type', '');
        if (str_contains($ct, 'json')) {
            try {
                $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $payload = [];
            }
        } else {
            $payload = $request->request->all();
        }

        if (!$this->isCsrfTokenValid('formation_reco_track', (string) ($payload['_token'] ?? ''))) {
            return new JsonResponse(['ok' => false, 'error' => 'csrf'], 403);
        }

        $formationId = (int) ($payload['formation_id'] ?? 0);
        $section = (string) ($payload['section'] ?? '');
        $eventType = (string) ($payload['event'] ?? FormationRecommendationEvent::EVENT_IMPRESSION);

        if ($formationId <= 0) {
            return new JsonResponse(['ok' => false, 'error' => 'formation_id'], 422);
        }

        if (!in_array($section, self::ALLOWED_SECTIONS, true)) {
            return new JsonResponse(['ok' => false, 'error' => 'section'], 422);
        }

        $allowedEvents = [
            FormationRecommendationEvent::EVENT_IMPRESSION,
            FormationRecommendationEvent::EVENT_CLICK,
            FormationRecommendationEvent::EVENT_ENROLL,
        ];
        if (!in_array($eventType, $allowedEvents, true)) {
            return new JsonResponse(['ok' => false, 'error' => 'event'], 422);
        }

        $formation = $this->entityManager->find(Formation::class, $formationId);
        if (!$formation instanceof Formation) {
            return new JsonResponse(['ok' => false, 'error' => 'not_found'], 404);
        }

        $ev = new FormationRecommendationEvent();
        $ev->setUser($user);
        $ev->setFormation($formation);
        $ev->setSection($section);
        $ev->setEventType($eventType);
        $ev->setCreatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($ev);
        $this->entityManager->flush();

        return new JsonResponse(['ok' => true]);
    }
}
