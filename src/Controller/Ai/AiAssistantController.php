<?php

namespace App\Controller\Ai;

use App\Model\Ai\ChatMessageInput;
use App\Service\AI\AiDatabaseAssistantService;
use App\Service\AI\AiNavigationBotService;
use App\Service\AI\CloudflareAiService;
use App\Service\Chatbot\RouterAgent;
use App\Service\Chatbot\NavAgent;
use App\Service\Chatbot\DBAgent;
use App\Service\Chatbot\GeneralAgent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/assistant')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class AiAssistantController extends AbstractController
{
    private const CHAT_HISTORY_SESSION_KEY = 'ai_chat_history';
    private const CSRF_MESSAGE_ID = 'ai_chat_message';
    private const CSRF_CLEAR_ID = 'ai_chat_clear';

    public function __construct(
        private readonly AiNavigationBotService $navigationBotService,
        private readonly AiDatabaseAssistantService $databaseAssistantService,
        private readonly CloudflareAiService $cloudflareAiService,
        private readonly RouterAgent $routerAgent,
        private readonly NavAgent $navAgent,
        private readonly DBAgent $dbAgent,
        private readonly GeneralAgent $generalAgent,
    ) {
    }

    #[Route('/chat', name: 'app_ai_chat_page', methods: ['GET'])]
    public function page(CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        return $this->render('front/ai/chat.html.twig', [
            'welcomeMessage' => "Welcome to BizHub AI assistant! Ask me to navigate or query your data.",
            'csrfMessageToken' => $csrfTokenManager->getToken(self::CSRF_MESSAGE_ID)->getValue(),
            'csrfClearToken' => $csrfTokenManager->getToken(self::CSRF_CLEAR_ID)->getValue(),
            'cloudflareConfigured' => $this->cloudflareAiService->isConfigured(),
        ]);
    }

    #[Route('/chat/message', name: 'app_ai_chat_message', methods: ['POST'])]
    public function message(
        Request $request,
        ValidatorInterface $validator,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): JsonResponse {
        $token = (string) $request->request->get('_token', '');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_MESSAGE_ID, $token))) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid CSRF token.',
            ], Response::HTTP_FORBIDDEN);
        }

        $input = (new ChatMessageInput())->setMessage((string) $request->request->get('message', ''));
        $errors = $validator->validate($input);

        if (count($errors) > 0) {
            return $this->json([
                'success' => false,
                'message' => $errors[0]->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        $userMessage = (string) $input->getMessage();
        $session = $request->getSession();
        $sessionId = $session->getId();
        $intent = $this->routerAgent->classify($userMessage);

        $agentResponse = match($intent) {
            'db'  => $this->dbAgent->reply($userMessage, $sessionId),
            'nav' => $this->navAgent->reply($userMessage, $sessionId),
            default => $this->generalAgent->reply($userMessage, $sessionId),
        };

        $this->appendHistory($session, 'user', $userMessage);
        $this->appendHistory($session, 'assistant', $agentResponse->text);

        // Build navigateUrl from first navLink if present
        $navigateUrl = $agentResponse->navLinks[0]['url'] ?? null;

        // Build navButtons array for frontend (label + url pairs)
        $navButtons = $agentResponse->navLinks;

        return $this->json([
            'success'     => true,
            'intent'      => $agentResponse->intent,
            'reply'       => $agentResponse->text,
            'navigateUrl' => $navigateUrl,
            'navLinks'    => $navButtons,
        ]);
    }

    #[Route('/chat/clear', name: 'app_ai_chat_clear', methods: ['POST'])]
    public function clear(Request $request, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        $token = (string) $request->request->get('_token', '');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_CLEAR_ID, $token))) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid CSRF token.',
            ], Response::HTTP_FORBIDDEN);
        }

        $request->getSession()->set(self::CHAT_HISTORY_SESSION_KEY, []);

        return $this->json([
            'success' => true,
            'message' => 'Chat cleared.',
        ]);
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function resolveNavigationReply(string $intent): array
    {
        $routeMap = [
            AiNavigationBotService::GO_TO_LOGIN => ['route' => 'app_login', 'label' => 'Login page'],
            AiNavigationBotService::GO_TO_SIGNUP => ['route' => 'app_register', 'label' => 'Signup page'],
            AiNavigationBotService::GO_TO_PROFILE => ['route' => 'app_user_profile', 'label' => 'Profile page'],
            AiNavigationBotService::GO_TO_USER_MANAGEMENT => ['route' => 'app_admin_user_index', 'label' => 'User management'],
            AiNavigationBotService::GO_TO_FORMATIONS => ['route' => 'app_front_formations_index', 'label' => 'Formations page'],
            AiNavigationBotService::GO_TO_REVIEWS => ['route' => 'app_avis_list', 'label' => 'Reviews page'],
            AiNavigationBotService::GO_BACK => ['route' => 'app_user_dashboard', 'label' => 'Dashboard'],
        ];

        if (!isset($routeMap[$intent])) {
            return ["I can help with navigation or data questions.", null];
        }

        $route = $routeMap[$intent]['route'];
        $url = $this->generateUrl($route);

        return [sprintf('Taking you to %s.', $routeMap[$intent]['label']), $url];
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function getHistory(SessionInterface $session): array
    {
        $history = $session->get(self::CHAT_HISTORY_SESSION_KEY, []);

        return is_array($history) ? $history : [];
    }

    private function appendHistory(SessionInterface $session, string $role, string $content): void
    {
        $history = $this->getHistory($session);
        $history[] = [
            'role' => $role,
            'content' => $content,
        ];

        if (count($history) > 20) {
            $history = array_slice($history, -20);
        }

        $session->set(self::CHAT_HISTORY_SESSION_KEY, $history);
    }
}
