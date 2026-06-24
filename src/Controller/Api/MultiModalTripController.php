<?php

namespace App\Controller\Api;

use App\Entity\MultiModalTrip;
use App\Entity\MultiModalTripSegment;
use App\Repository\MultiModalTripRepository;
use App\Service\MultiModalTripService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/multi-modal-trips')]
class MultiModalTripController extends AbstractController
{
    public function __construct(
        private MultiModalTripRepository $multiModalTripRepository,
        private MultiModalTripService $multiModalTripService
    ) {
    }

    #[Route('', name: 'api_multi_modal_trips_list', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['success' => false, 'message' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }

        $items = $this->multiModalTripRepository->findByUser($user->getId());
        $data = array_map(fn (MultiModalTrip $m) => $this->serializeMultiModal($m), $items);

        return $this->json(['success' => true, 'data' => $data]);
    }

    #[Route('', name: 'api_multi_modal_trips_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['success' => false, 'message' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        foreach (['departureCity', 'arrivalCity', 'departureTime'] as $f) {
            if (empty($payload[$f])) {
                return $this->json(['success' => false, 'message' => $f.' is required.'], Response::HTTP_BAD_REQUEST);
            }
        }

        try {
            $departureTime = new \DateTimeImmutable($payload['departureTime']);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Invalid departureTime.'], Response::HTTP_BAD_REQUEST);
        }

        $preferences = is_array($payload['preferences'] ?? null) ? $payload['preferences'] : [];

        $mmt = $this->multiModalTripService->planTrip(
            $user,
            (string) $payload['departureCity'],
            (string) $payload['arrivalCity'],
            $departureTime,
            $preferences
        );

        return $this->json([
            'success' => true,
            'message' => 'Multi-modal trip planned.',
            'data' => $this->serializeMultiModal($mmt),
        ], Response::HTTP_CREATED);
    }

    private function serializeMultiModal(MultiModalTrip $m): array
    {
        $segments = [];
        foreach ($m->getSegments() as $seg) {
            /** @var MultiModalTripSegment $seg */
            $segments[] = [
                'id' => $seg->getId(),
                'segmentOrder' => $seg->getSegmentOrder(),
                'departureCity' => $seg->getDepartureCity(),
                'arrivalCity' => $seg->getArrivalCity(),
                'departureTime' => $seg->getDepartureTime()->format('Y-m-d H:i:s'),
                'arrivalTime' => $seg->getArrivalTime()?->format('Y-m-d H:i:s'),
                'price' => $seg->getPrice(),
                'status' => $seg->getStatus(),
                'trip' => $seg->getTrip() ? [
                    'id' => $seg->getTrip()->getId(),
                    'departureCity' => $seg->getTrip()->getDepartureCity(),
                    'arrivalCity' => $seg->getTrip()->getArrivalCity(),
                    'price' => $seg->getTrip()->getPrice(),
                ] : null,
            ];
        }

        return [
            'id' => $m->getId(),
            'departureCity' => $m->getDepartureCity(),
            'arrivalCity' => $m->getArrivalCity(),
            'departureTime' => $m->getDepartureTime()->format('Y-m-d H:i:s'),
            'arrivalTime' => $m->getArrivalTime()?->format('Y-m-d H:i:s'),
            'totalPrice' => $m->getTotalPrice(),
            'totalDuration' => $m->getTotalDuration(),
            'totalDistance' => $m->getTotalDistance(),
            'status' => $m->getStatus(),
            'segments' => $segments,
        ];
    }
}
