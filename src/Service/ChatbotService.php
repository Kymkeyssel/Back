<?php

namespace App\Service;

use App\Entity\ChatConversation;
use App\Entity\ChatMessage;
use App\Entity\User;
use App\Repository\ChatConversationRepository;
use App\Repository\ChatMessageRepository;
use App\Repository\TripRepository;
use App\Repository\AgencyRepository;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ChatbotService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ChatConversationRepository $conversationRepository,
        private ChatMessageRepository $messageRepository,
        private TripRepository $tripRepository,
        private AgencyRepository $agencyRepository,
        private BookingRepository $bookingRepository,
        private HttpClientInterface $httpClient,
        private TranslationService $translationService,
        string $openaiApiKey = '',
        string $openaiModel = 'gpt-4',
        string $openaiBaseUrl = 'https://api.openai.com/v1'
    ) {
        $this->apiKey = $openaiApiKey;
        $this->model = $openaiModel;
        $this->baseUrl = $openaiBaseUrl;
    }

    /**
     * Create a new conversation
     */
    public function createConversation(User $user, string $title = 'New Conversation'): ChatConversation
    {
        $conversation = new ChatConversation();
        $conversation->setUser($user);
        $conversation->setTitle($title);

        $this->conversationRepository->save($conversation, true);

        return $conversation;
    }

    /**
     * Get or create conversation for user
     */
    public function getOrCreateConversation(User $user, ?int $conversationId = null): ChatConversation
    {
        if ($conversationId) {
            $conversation = $this->conversationRepository->findByUserAndId($user, $conversationId);
            if ($conversation) {
                return $conversation;
            }
        }

        return $this->createConversation($user);
    }

    /**
     * Send message and get AI response
     */
    public function sendMessage(User $user, string $content, ?int $conversationId = null, string $language = 'fr'): array
    {
        $conversation = $this->getOrCreateConversation($user, $conversationId);

        // Save user message
        $userMessage = new ChatMessage();
        $userMessage->setConversation($conversation);
        $userMessage->setRole('user');
        $userMessage->setContent($content);
        $this->messageRepository->save($userMessage, true);

        // Get conversation history
        $messages = $this->messageRepository->findByConversation($conversation);
        $messageHistory = $this->buildMessageHistory($messages, $language);

        // Get AI response
        $aiResponse = $this->getAIResponse($messageHistory, $language);

        // Save AI response
        $assistantMessage = new ChatMessage();
        $assistantMessage->setConversation($conversation);
        $assistantMessage->setRole('assistant');
        $assistantMessage->setContent($aiResponse['content']);
        $assistantMessage->setMetadata($aiResponse['metadata'] ?? null);
        $this->messageRepository->save($assistantMessage, true);

        // Update conversation
        $conversation->setLastMessageAt(new \DateTimeImmutable());
        $conversation->setTitle($this->generateConversationTitle($content));
        $this->conversationRepository->save($conversation, true);

        return [
            'conversation_id' => $conversation->getId(),
            'message' => [
                'id' => $assistantMessage->getId(),
                'role' => 'assistant',
                'content' => $aiResponse['content'],
                'created_at' => $assistantMessage->getCreatedAt()->format('c'),
            ],
            'metadata' => $aiResponse['metadata'] ?? null,
        ];
    }

    /**
     * Get conversation history
     */
    public function getConversationHistory(User $user, int $conversationId): array
    {
        $conversation = $this->conversationRepository->findByUserAndId($user, $conversationId);
        if (!$conversation) {
            throw new \InvalidArgumentException('Conversation not found');
        }

        $messages = $this->messageRepository->findByConversation($conversation);

        return [
            'conversation' => [
                'id' => $conversation->getId(),
                'title' => $conversation->getTitle(),
                'created_at' => $conversation->getCreatedAt()->format('c'),
                'last_message_at' => $conversation->getLastMessageAt()?->format('c'),
            ],
            'messages' => array_map(fn(ChatMessage $message) => [
                'id' => $message->getId(),
                'role' => $message->getRole(),
                'content' => $message->getContent(),
                'metadata' => $message->getMetadata(),
                'created_at' => $message->getCreatedAt()->format('c'),
            ], $messages),
        ];
    }

    /**
     * Get user conversations
     */
    public function getUserConversations(User $user): array
    {
        $conversations = $this->conversationRepository->findByUser($user);

        return array_map(fn(ChatConversation $conversation) => [
            'id' => $conversation->getId(),
            'title' => $conversation->getTitle(),
            'message_count' => $this->messageRepository->countByConversation($conversation),
            'last_message' => $this->getLastMessagePreview($conversation),
            'created_at' => $conversation->getCreatedAt()->format('c'),
            'last_message_at' => $conversation->getLastMessageAt()?->format('c'),
        ], $conversations);
    }

    /**
     * Delete conversation
     */
    public function deleteConversation(User $user, int $conversationId): bool
    {
        $conversation = $this->conversationRepository->findByUserAndId($user, $conversationId);
        if (!$conversation) {
            return false;
        }

        $conversation->setIsActive(false);
        $this->conversationRepository->save($conversation, true);

        return true;
    }

    /**
     * Build message history for AI
     */
    private function buildMessageHistory(array $messages, string $language): array
    {
        $history = [];

        // Add system prompt
        $history[] = [
            'role' => 'system',
            'content' => $this->getSystemPrompt($language),
        ];

        // Add conversation history (last 20 messages)
        $recentMessages = array_slice($messages, -20);
        foreach ($recentMessages as $message) {
            $history[] = [
                'role' => $message->getRole(),
                'content' => $message->getContent(),
            ];
        }

        return $history;
    }

    /**
     * Get system prompt
     */
    private function getSystemPrompt(string $language): string
    {
        $prompts = [
            'fr' => "Tu es l'assistant virtuel de TransCam, une plateforme de réservation de transport au Cameroun. 
Tu aides les utilisateurs avec:
- La recherche et réservation de trajets
- Les informations sur les agences de transport
- Le suivi de leurs réservations
- Les questions sur les paiements (MTN Mobile Money, Orange Money, carte bancaire)
- Les problèmes techniques

Sois amical, serviable et précis. Réponds toujours en français. 
Si tu ne connais pas la réponse, dis-le clairement et suggère de contacter le support client.

Tu peux accéder aux données de la plateforme pour aider l'utilisateur.",
            'en' => "You are the virtual assistant for TransCam, a transport booking platform in Cameroon.
You help users with:
- Searching and booking trips
- Information about transport agencies
- Tracking their bookings
- Payment questions (MTN Mobile Money, Orange Money, credit card)
- Technical issues

Be friendly, helpful and precise. Always respond in English.
If you don't know the answer, say so clearly and suggest contacting customer support.

You can access platform data to help the user.",
        ];

        return $prompts[$language] ?? $prompts['fr'];
    }

    /**
     * Get AI response from OpenAI
     */
    private function getAIResponse(array $messages, string $language): array
    {
        if (empty($this->apiKey)) {
            return $this->getFallbackResponse($messages, $language);
        }

        try {
            $response = $this->httpClient->request('POST', $this->baseUrl . '/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'messages' => $messages,
                    'max_tokens' => 1000,
                    'temperature' => 0.7,
                ],
            ]);

            $data = $response->toArray(false);

            if (isset($data['choices'][0]['message']['content'])) {
                return [
                    'content' => $data['choices'][0]['message']['content'],
                    'metadata' => [
                        'model' => $data['model'] ?? $this->model,
                        'usage' => $data['usage'] ?? null,
                    ],
                ];
            }
        } catch (\Exception $e) {
            // Fall back to local response
        }

        return $this->getFallbackResponse($messages, $language);
    }

    /**
     * Get fallback response when AI is unavailable
     */
    private function getFallbackResponse(array $messages, string $language): array
    {
        $lastMessage = end($messages);
        $userContent = strtolower($lastMessage['content'] ?? '');

        // Simple keyword-based responses
        $responses = [
            'fr' => [
                'bonjour' => 'Bonjour ! Je suis l\'assistant TransCam. Comment puis-je vous aider aujourd\'hui ?',
                'recherche' => 'Je peux vous aider à rechercher des trajets. Quelle est votre ville de départ et votre destination ?',
                'reservation' => 'Pour réserver un trajet, vous pouvez utiliser la fonction de recherche sur notre plateforme. Avez-vous besoin d\'aide pour trouver un trajet spécifique ?',
                'paiement' => 'Nous acceptons les paiements par MTN Mobile Money, Orange Money et carte bancaire. Quelle méthode préférez-vous ?',
                'default' => 'Je suis là pour vous aider avec vos réservations de transport. Que souhaitez-vous savoir ?',
            ],
            'en' => [
                'hello' => 'Hello! I am the TransCam assistant. How can I help you today?',
                'search' => 'I can help you search for trips. What is your departure city and destination?',
                'booking' => 'To book a trip, you can use the search function on our platform. Do you need help finding a specific trip?',
                'payment' => 'We accept payments via MTN Mobile Money, Orange Money and credit card. Which method do you prefer?',
                'default' => 'I am here to help you with your transport bookings. What would you like to know?',
            ],
        ];

        $langResponses = $responses[$language] ?? $responses['fr'];

        foreach ($langResponses as $keyword => $response) {
            if ($keyword !== 'default' && str_contains($userContent, $keyword)) {
                return ['content' => $response];
            }
        }

        return ['content' => $langResponses['default']];
    }

    /**
     * Generate conversation title from first message
     */
    private function generateConversationTitle(string $content): string
    {
        $title = substr($content, 0, 50);
        if (strlen($content) > 50) {
            $title .= '...';
        }
        return $title;
    }

    /**
     * Get last message preview
     */
    private function getLastMessagePreview(ChatConversation $conversation): ?array
    {
        $lastMessage = $this->messageRepository->findLastByConversation($conversation);
        if (!$lastMessage) {
            return null;
        }

        return [
            'role' => $lastMessage->getRole(),
            'content' => substr($lastMessage->getContent(), 0, 100),
            'created_at' => $lastMessage->getCreatedAt()->format('c'),
        ];
    }
}
