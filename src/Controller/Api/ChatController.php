<?php

namespace App\Controller\Api;

use App\Service\ChatbotService;
use App\Service\TranslationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/chat')]
#[IsGranted('ROLE_USER')]
class ChatController extends AbstractController
{
    public function __construct(
        private ChatbotService $chatbotService,
        private TranslationService $translationService
    ) {
    }

    /**
     * Send a message to the chatbot
     */
    #[Route('/message', name: 'api_chat_send_message', methods: ['POST'])]
    public function sendMessage(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['message']) || empty(trim($data['message']))) {
            return $this->json([
                'success' => false,
                'message' => 'Message is required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->getUser();
        $language = $data['language'] ?? $user->getPreferredLanguage() ?? 'fr';
        $conversationId = $data['conversation_id'] ?? null;

        try {
            $result = $this->chatbotService->sendMessage(
                $user,
                trim($data['message']),
                $conversationId,
                $language
            );

            return $this->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to process message.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get conversation history
     */
    #[Route('/conversations/{id}', name: 'api_chat_get_conversation', methods: ['GET'])]
    public function getConversation(int $id): JsonResponse
    {
        $user = $this->getUser();

        try {
            $history = $this->chatbotService->getConversationHistory($user, $id);

            return $this->json([
                'success' => true,
                'data' => $history,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to retrieve conversation.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get all user conversations
     */
    #[Route('/conversations', name: 'api_chat_get_conversations', methods: ['GET'])]
    public function getConversations(): JsonResponse
    {
        $user = $this->getUser();

        try {
            $conversations = $this->chatbotService->getUserConversations($user);

            return $this->json([
                'success' => true,
                'data' => $conversations,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to retrieve conversations.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create a new conversation
     */
    #[Route('/conversations', name: 'api_chat_create_conversation', methods: ['POST'])]
    public function createConversation(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $user = $this->getUser();
        $title = $data['title'] ?? 'New Conversation';

        try {
            $conversation = $this->chatbotService->createConversation($user, $title);

            return $this->json([
                'success' => true,
                'data' => [
                    'id' => $conversation->getId(),
                    'title' => $conversation->getTitle(),
                    'created_at' => $conversation->getCreatedAt()->format('c'),
                ],
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to create conversation.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a conversation
     */
    #[Route('/conversations/{id}', name: 'api_chat_delete_conversation', methods: ['DELETE'])]
    public function deleteConversation(int $id): JsonResponse
    {
        $user = $this->getUser();

        try {
            $deleted = $this->chatbotService->deleteConversation($user, $id);

            if (!$deleted) {
                return $this->json([
                    'success' => false,
                    'message' => 'Conversation not found.',
                ], Response::HTTP_NOT_FOUND);
            }

            return $this->json([
                'success' => true,
                'message' => 'Conversation deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to delete conversation.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get quick suggestions
     */
    #[Route('/suggestions', name: 'api_chat_get_suggestions', methods: ['GET'])]
    public function getSuggestions(Request $request): JsonResponse
    {
        $language = $request->query->get('language', 'fr');

        $suggestions = [
            'fr' => [
                'Je veux réserver un trajet de Douala à Yaoundé',
                'Quels sont les horaires disponibles ?',
                'Comment puis-je payer ?',
                'Où est mon billet ?',
                'Annuler ma réservation',
                'Contacter le support',
            ],
            'en' => [
                'I want to book a trip from Douala to Yaoundé',
                'What are the available schedules?',
                'How can I pay?',
                'Where is my ticket?',
                'Cancel my booking',
                'Contact support',
            ],
        ];

        return $this->json([
            'success' => true,
            'data' => $suggestions[$language] ?? $suggestions['fr'],
        ]);
    }

    /**
     * Get chatbot status
     */
    #[Route('/status', name: 'api_chat_status', methods: ['GET'])]
    public function getStatus(): JsonResponse
    {
        return $this->json([
            'success' => true,
            'data' => [
                'status' => 'online',
                'version' => '1.0.0',
                'features' => [
                    'multilingual' => true,
                    'conversation_history' => true,
                    'quick_suggestions' => true,
                ],
                'supported_languages' => ['fr', 'en'],
            ],
        ]);
    }
}
