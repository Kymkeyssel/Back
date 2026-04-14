<?php

namespace App\Controller\Api;

use App\Entity\Agency;
use App\Entity\Trip;
use App\Service\DelayPredictionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/delays')]
class DelayPredictionController extends AbstractController
{
    public function __construct(
        private DelayPredictionService $delayPredictionService,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Predict delay for a trip
     */
    #[Route('/predict/{id}', name: 'api_delay_predict', methods: ['GET'])]
    public function predictDelay(int $id): JsonResponse
    {
        $trip = $this->entityManager->getRepository(Trip::class)->find($id);

        if (!$trip) {
            return $this->json([
                'success' => false,
                'message' => 'Trip not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $prediction = $this->delayPredictionService->predictDelay($trip);

            return $this->json([
                'success' => true,
                'data' => $this->serializePrediction($prediction),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to predict delay.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get delay predictions for a trip
     */
    #[Route('/predictions/{tripId}', name: 'api_delay_predictions', methods: ['GET'])]
    public function getPredictions(int $tripId): JsonResponse
    {
        $trip = $this->entityManager->getRepository(Trip::class)->find($tripId);

        if (!$trip) {
            return $this->json([
                'success' => false,
                'message' => 'Trip not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $predictions = $this->delayPredictionService->getTripPredictions($trip);

        return $this->json([
            'success' => true,
            'data' => array_map(fn($prediction) => $this->serializePrediction($prediction), $predictions),
        ]);
    }

    /**
     * Get latest prediction for a trip
     */
    #[Route('/predictions/{tripId}/latest', name: 'api_delay_prediction_latest', methods: ['GET'])]
    public function getLatestPrediction(int $tripId): JsonResponse
    {
        $trip = $this->entityManager->getRepository(Trip::class)->find($tripId);

        if (!$trip) {
            return $this->json([
                'success' => false,
                'message' => 'Trip not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $prediction = $this->delayPredictionService->getLatestPrediction($trip);

        if (!$prediction) {
            return $this->json([
                'success' => false,
                'message' => 'No predictions found for this trip.',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => $this->serializePrediction($prediction),
        ]);
    }

    /**
     * Record actual delay for a trip
     */
    #[Route('/record/{tripId}', name: 'api_delay_record', methods: ['POST'])]
    #[IsGranted('ROLE_AGENCY')]
    public function recordDelay(int $tripId, Request $request): JsonResponse
    {
        $trip = $this->entityManager->getRepository(Trip::class)->find($tripId);

        if (!$trip) {
            return $this->json([
                'success' => false,
                'message' => 'Trip not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($trip->getAgency()->getOwner() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json([
                'success' => false,
                'message' => 'Access denied.',
            ], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        // Validate required fields
        if (!isset($data['delay_minutes']) || !is_numeric($data['delay_minutes'])) {
            return $this->json([
                'success' => false,
                'message' => 'Delay minutes is required and must be a number.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $history = $this->delayPredictionService->recordActualDelay(
                $trip,
                (int) $data['delay_minutes'],
                $data['reason'] ?? null,
                $data['conditions'] ?? []
            );

            return $this->json([
                'success' => true,
                'message' => 'Delay recorded successfully.',
                'data' => $this->serializeHistory($history),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to record delay.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get delay history for a trip
     */
    #[Route('/history/{tripId}', name: 'api_delay_history', methods: ['GET'])]
    public function getDelayHistory(int $tripId): JsonResponse
    {
        $trip = $this->entityManager->getRepository(Trip::class)->find($tripId);

        if (!$trip) {
            return $this->json([
                'success' => false,
                'message' => 'Trip not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $history = $this->delayPredictionService->getTripDelayHistory($trip);

        return $this->json([
            'success' => true,
            'data' => array_map(fn($record) => $this->serializeHistory($record), $history),
        ]);
    }

    /**
     * Get delay statistics for an agency
     */
    #[Route('/statistics/agency/{agencyId}', name: 'api_delay_statistics_agency', methods: ['GET'])]
    #[IsGranted('ROLE_AGENCY')]
    public function getAgencyStatistics(int $agencyId): JsonResponse
    {
        $agency = $this->entityManager->getRepository(Agency::class)->find($agencyId);

        if (!$agency) {
            return $this->json([
                'success' => false,
                'message' => 'Agency not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($agency->getOwner() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json([
                'success' => false,
                'message' => 'Access denied.',
            ], Response::HTTP_FORBIDDEN);
        }

        $statistics = $this->delayPredictionService->getDelayStatistics($agency);

        return $this->json([
            'success' => true,
            'data' => $statistics,
        ]);
    }

    /**
     * Get delay trends for an agency
     */
    #[Route('/trends/agency/{agencyId}', name: 'api_delay_trends_agency', methods: ['GET'])]
    #[IsGranted('ROLE_AGENCY')]
    public function getAgencyTrends(int $agencyId, Request $request): JsonResponse
    {
        $agency = $this->entityManager->getRepository(Agency::class)->find($agencyId);

        if (!$agency) {
            return $this->json([
                'success' => false,
                'message' => 'Agency not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($agency->getOwner() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json([
                'success' => false,
                'message' => 'Access denied.',
            ], Response::HTTP_FORBIDDEN);
        }

        $period = $request->query->get('period', 'month');
        $trends = $this->delayPredictionService->getDelayTrends($agency, $period);

        return $this->json([
            'success' => true,
            'data' => $trends,
        ]);
    }

    /**
     * Get common delay reasons
     */
    #[Route('/reasons', name: 'api_delay_reasons', methods: ['GET'])]
    public function getCommonReasons(Request $request): JsonResponse
    {
        $limit = $request->query->getInt('limit', 10);
        $reasons = $this->delayPredictionService->getCommonDelayReasons($limit);

        return $this->json([
            'success' => true,
            'data' => $reasons,
        ]);
    }

    /**
     * Serialize prediction
     */
    private function serializePrediction($prediction): array
    {
        return [
            'id' => $prediction->getId(),
            'trip_id' => $prediction->getTrip()->getId(),
            'predicted_delay_minutes' => $prediction->getPredictedDelayMinutes(),
            'confidence_score' => $prediction->getConfidenceScore(),
            'risk_level' => $prediction->getRiskLevel(),
            'factors' => $prediction->getFactors(),
            'reasoning' => $prediction->getReasoning(),
            'predicted_at' => $prediction->getPredictedAt()?->format('c'),
            'actual_delay_minutes' => $prediction->getActualDelayMinutes(),
            'actual_arrival_time' => $prediction->getActualArrivalTime()?->format('c'),
            'is_accurate' => $prediction->isAccurate(),
            'created_at' => $prediction->getCreatedAt()->format('c'),
        ];
    }

    /**
     * Serialize history
     */
    private function serializeHistory($history): array
    {
        return [
            'id' => $history->getId(),
            'trip_id' => $history->getTrip()->getId(),
            'delay_minutes' => $history->getDelayMinutes(),
            'reason' => $history->getReason(),
            'conditions' => $history->getConditions(),
            'occurred_at' => $history->getOccurredAt()?->format('c'),
            'created_at' => $history->getCreatedAt()->format('c'),
        ];
    }
}
