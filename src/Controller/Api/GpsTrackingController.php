<?php

namespace App\Controller\Api;

use App\Service\GpsTrackingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GpsTrackingController extends AbstractController
{
    public function __construct(
        private GpsTrackingService $gpsTrackingService,
    ) {
    }

    #[Route('/api/trips/{id}/gps', name: 'api_trip_gps', methods: ['GET'])]
    public function gps(int $id): JsonResponse
    {
        $data = $this->gpsTrackingService->getGpsData($id);

        if (!$data) {
            return $this->json([
                'success' => false,
                'message' => 'Trip not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => [
                'tripId' => $data->tripId,
                'departure' => $data->departure,
                'arrival' => $data->arrival,
                'status' => $data->status,
                'departureTime' => $data->departureTime,
                'arrivalTime' => $data->arrivalTime,
            ],
        ]);
    }
}
