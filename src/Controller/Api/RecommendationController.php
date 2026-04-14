<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\RecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/recommendations')]
#[IsGranted('ROLE_USER')]
class RecommendationController extends AbstractController
{
    public function __construct(
        private RecommendationService $recommendationService,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Get personalized recommendations
     */
    #[Route('', name: 'api_recommendations_get', methods: ['GET'])]
    public function getRecommendations(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $type = $request->query->get('type', 'trip');
        $limit = $request->query->getInt('limit', 10);

        if (!in_array($type, ['trip', 'agency', 'route', 'offer'])) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid recommendation type.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $recommendations = $this->recommendationService->getRecommendations($user, $type, $limit);

            return $this->json([
                'success' => true,
                'data' => $recommendations,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to get recommendations.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get trip recommendations
     */
    #[Route('/trips', name: 'api_recommendations_trips', methods: ['GET'])]
    public function getTripRecommendations(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $limit = $request->query->getInt('limit', 10);

        try {
            $recommendations = $this->recommendationService->getRecommendations($user, 'trip', $limit);

            return $this->json([
                'success' => true,
                'data' => $recommendations,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to get trip recommendations.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get agency recommendations
     */
    #[Route('/agencies', name: 'api_recommendations_agencies', methods: ['GET'])]
    public function getAgencyRecommendations(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $limit = $request->query->getInt('limit', 10);

        try {
            $recommendations = $this->recommendationService->getRecommendations($user, 'agency', $limit);

            return $this->json([
                'success' => true,
                'data' => $recommendations,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to get agency recommendations.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get route recommendations
     */
    #[Route('/routes', name: 'api_recommendations_routes', methods: ['GET'])]
    public function getRouteRecommendations(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $limit = $request->query->getInt('limit', 10);

        try {
            $recommendations = $this->recommendationService->getRecommendations($user, 'route', $limit);

            return $this->json([
                'success' => true,
                'data' => $recommendations,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to get route recommendations.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get offer recommendations
     */
    #[Route('/offers', name: 'api_recommendations_offers', methods: ['GET'])]
    public function getOfferRecommendations(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $limit = $request->query->getInt('limit', 10);

        try {
            $recommendations = $this->recommendationService->getRecommendations($user, 'offer', $limit);

            return $this->json([
                'success' => true,
                'data' => $recommendations,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to get offer recommendations.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Mark recommendation as clicked
     */
    #[Route('/{id}/click', name: 'api_recommendation_click', methods: ['POST'])]
    public function markAsClicked(int $id): JsonResponse
    {
        $user = $this->getUser();

        try {
            $this->recommendationService->markAsClicked($id, $user);

            return $this->json([
                'success' => true,
                'message' => 'Recommendation marked as clicked.',
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to mark recommendation as clicked.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Mark recommendation as booked
     */
    #[Route('/{id}/book', name: 'api_recommendation_book', methods: ['POST'])]
    public function markAsBooked(int $id): JsonResponse
    {
        $user = $this->getUser();

        try {
            $this->recommendationService->markAsBooked($id, $user);

            return $this->json([
                'success' => true,
                'message' => 'Recommendation marked as booked.',
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to mark recommendation as booked.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get user preferences
     */
    #[Route('/preferences', name: 'api_recommendations_preferences', methods: ['GET'])]
    public function getPreferences(): JsonResponse
    {
        $user = $this->getUser();

        try {
            $preferences = $this->recommendationService->getUserPreferences($user);

            return $this->json([
                'success' => true,
                'data' => $preferences ? $this->serializePreferences($preferences) : null,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to get user preferences.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update user preferences
     */
    #[Route('/preferences', name: 'api_recommendations_preferences_update', methods: ['PUT'])]
    public function updatePreferences(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        try {
            $preferences = $this->recommendationService->updateUserPreferences($user, $data);

            return $this->json([
                'success' => true,
                'message' => 'Preferences updated successfully.',
                'data' => $this->serializePreferences($preferences),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to update preferences.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get recommendation statistics
     */
    #[Route('/statistics', name: 'api_recommendations_statistics', methods: ['GET'])]
    public function getStatistics(): JsonResponse
    {
        $user = $this->getUser();

        try {
            $statistics = $this->recommendationService->getRecommendationStatistics($user);

            return $this->json([
                'success' => true,
                'data' => $statistics,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to get recommendation statistics.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Serialize preferences
     */
    private function serializePreferences($preferences): array
    {
        return [
            'id' => $preferences->getId(),
            'preferred_routes' => $preferences->getPreferredRoutes(),
            'preferred_agencies' => $preferences->getPreferredAgencies(),
            'preferred_vehicle_types' => $preferences->getPreferredVehicleTypes(),
            'preferred_time_slots' => $preferences->getPreferredTimeSlots(),
            'preferred_amenities' => $preferences->getPreferredAmenities(),
            'budget_range' => $preferences->getBudgetRange(),
            'prefers_eco_friendly' => $preferences->isPrefersEcoFriendly(),
            'prefers_express' => $preferences->isPrefersExpress(),
            'notification_preferences' => $preferences->getNotificationPreferences(),
            'created_at' => $preferences->getCreatedAt()->format('c'),
            'updated_at' => $preferences->getUpdatedAt()->format('c'),
        ];
    }
}
