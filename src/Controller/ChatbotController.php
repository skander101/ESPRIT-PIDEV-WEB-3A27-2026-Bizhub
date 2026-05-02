<?php

namespace App\Controller;

use App\Service\Chatbot\DBAgent;
use App\Service\Chatbot\GeneralAgent;
use App\Service\Chatbot\NavAgent;
use App\Service\Chatbot\RouterAgent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ChatbotController extends AbstractController
{
    public function __construct(
        private readonly RouterAgent $routerAgent,
        private readonly DBAgent $dbAgent,
        private readonly NavAgent $navAgent,
        private readonly GeneralAgent $generalAgent,
    ) {}

    #[Route('/chatbot/message', name: 'app_chatbot_message', methods: ['POST'])]
    public function handleMessage(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!is_array($data) || empty(trim($data['message'] ?? ''))) {
                return new JsonResponse(['error' => 'Missing or empty message'], JsonResponse::HTTP_BAD_REQUEST);
            }

            $message = trim($data['message']);
            $sessionId = $data['session_id'] ?? $request->getSession()->getId();

            $intent = $this->routerAgent->classify($message);

            match ($intent) {
                'db' => $response = $this->dbAgent->reply($message, $sessionId),
                'nav' => $response = $this->navAgent->reply($message, $sessionId),
                default => $response = $this->generalAgent->reply($message, $sessionId),
            };

            return new JsonResponse([
                'response' => $response->text,
                'intent' => $response->intent,
                'navLinks' => $response->navLinks,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
