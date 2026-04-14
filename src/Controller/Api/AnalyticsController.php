<?php

namespace App\Controller\Api;

use App\Entity\Agency;
use App\Entity\AnalyticsDashboard;
use App\Service\AnalyticsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/analytics')]
#[IsGranted('ROLE_USER')]
class AnalyticsController extends AbstractController
{
    public function __construct(
        private AnalyticsService $analyticsService,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Get user dashboards
     */
    #[Route('/dashboards', name: 'api_analytics_dashboards', methods: ['GET'])]
    public function getDashboards(): JsonResponse
    {
        $user = $this->getUser();
        $dashboards = $this->analyticsService->getUserDashboards($user);

        return $this->json([
            'success' => true,
            'data' => $dashboards,
        ]);
    }

    /**
     * Create a new dashboard
     */
    #[Route('/dashboards', name: 'api_analytics_dashboard_create', methods: ['POST'])]
    public function createDashboard(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate required fields
        if (!isset($data['name']) || empty(trim($data['name']))) {
            return $this->json([
                'success' => false,
                'message' => 'Dashboard name is required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!isset($data['type']) || !in_array($data['type'], ['agency', 'admin', 'user', 'custom'])) {
            return $this->json([
                'success' => false,
                'message' => 'Valid dashboard type is required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $dashboard = $this->analyticsService->createDashboard($this->getUser(), $data);

            return $this->json([
                'success' => true,
                'message' => 'Dashboard created successfully.',
                'data' => $this->serializeDashboard($dashboard),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to create dashboard.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get dashboard by ID
     */
    #[Route('/dashboards/{id}', name: 'api_analytics_dashboard_show', methods: ['GET'])]
    public function getDashboard(int $id): JsonResponse
    {
        $dashboard = $this->entityManager->getRepository(AnalyticsDashboard::class)->find($id);

        if (!$dashboard) {
            return $this->json([
                'success' => false,
                'message' => 'Dashboard not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Check access
        if ($dashboard->getUser() !== $this->getUser() && !$dashboard->isPublic() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json([
                'success' => false,
                'message' => 'Access denied.',
            ], Response::HTTP_FORBIDDEN);
        }

        $data = $this->analyticsService->getDashboardWithMetrics($dashboard);

        return $this->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Update dashboard
     */
    #[Route('/dashboards/{id}', name: 'api_analytics_dashboard_update', methods: ['PUT'])]
    public function updateDashboard(int $id, Request $request): JsonResponse
    {
        $dashboard = $this->entityManager->getRepository(AnalyticsDashboard::class)->find($id);

        if (!$dashboard) {
            return $this->json([
                'success' => false,
                'message' => 'Dashboard not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($dashboard->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json([
                'success' => false,
                'message' => 'Access denied.',
            ], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        try {
            $dashboard = $this->analyticsService->updateDashboard($dashboard, $data);

            return $this->json([
                'success' => true,
                'message' => 'Dashboard updated successfully.',
                'data' => $this->serializeDashboard($dashboard),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to update dashboard.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete dashboard
     */
    #[Route('/dashboards/{id}', name: 'api_analytics_dashboard_delete', methods: ['DELETE'])]
    public function deleteDashboard(int $id): JsonResponse
    {
        $dashboard = $this->entityManager->getRepository(AnalyticsDashboard::class)->find($id);

        if (!$dashboard) {
            return $this->json([
                'success' => false,
                'message' => 'Dashboard not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($dashboard->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json([
                'success' => false,
                'message' => 'Access denied.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $this->entityManager->remove($dashboard);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Dashboard deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to delete dashboard.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get revenue metrics for agency
     */
    #[Route('/metrics/revenue/{agencyId}', name: 'api_analytics_revenue', methods: ['GET'])]
    #[IsGranted('ROLE_AGENCY')]
    public function getRevenueMetrics(int $agencyId, Request $request): JsonResponse
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
        $metrics = $this->analyticsService->calculateRevenueMetrics($agency, $period);

        return $this->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }

    /**
     * Get booking metrics for agency
     */
    #[Route('/metrics/bookings/{agencyId}', name: 'api_analytics_bookings', methods: ['GET'])]
    #[IsGranted('ROLE_AGENCY')]
    public function getBookingMetrics(int $agencyId, Request $request): JsonResponse
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
        $metrics = $this->analyticsService->calculateBookingMetrics($agency, $period);

        return $this->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }

    /**
     * Get trip metrics for agency
     */
    #[Route('/metrics/trips/{agencyId}', name: 'api_analytics_trips', methods: ['GET'])]
    #[IsGranted('ROLE_AGENCY')]
    public function getTripMetrics(int $agencyId, Request $request): JsonResponse
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
        $metrics = $this->analyticsService->calculateTripMetrics($agency, $period);

        return $this->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }

    /**
     * Get occupancy rate for agency
     */
    #[Route('/metrics/occupancy/{agencyId}', name: 'api_analytics_occupancy', methods: ['GET'])]
    #[IsGranted('ROLE_AGENCY')]
    public function getOccupancyRate(int $agencyId, Request $request): JsonResponse
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
        $metrics = $this->analyticsService->calculateOccupancyRate($agency, $period);

        return $this->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }

    /**
     * Get top routes for agency
     */
    #[Route('/metrics/top-routes/{agencyId}', name: 'api_analytics_top_routes', methods: ['GET'])]
    #[IsGranted('ROLE_AGENCY')]
    public function getTopRoutes(int $agencyId, Request $request): JsonResponse
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

        $limit = $request->query->getInt('limit', 10);
        $routes = $this->analyticsService->getTopRoutes($agency, $limit);

        return $this->json([
            'success' => true,
            'data' => $routes,
        ]);
    }

    /**
     * Get revenue by date range
     */
    #[Route('/metrics/revenue-range/{agencyId}', name: 'api_analytics_revenue_range', methods: ['GET'])]
    #[IsGranted('ROLE_AGENCY')]
    public function getRevenueByDateRange(int $agencyId, Request $request): JsonResponse
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

        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');

        if (!$startDate || !$endDate) {
            return $this->json([
                'success' => false,
                'message' => 'Start date and end date are required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $start = new \DateTimeImmutable($startDate);
            $end = new \DateTimeImmutable($endDate);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid date format.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $metrics = $this->analyticsService->getRevenueByDateRange($agency, $start, $end);

        return $this->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }

    /**
     * Serialize dashboard
     */
    private function serializeDashboard(AnalyticsDashboard $dashboard): array
    {
        return [
            'id' => $dashboard->getId(),
            'name' => $dashboard->getName(),
            'type' => $dashboard->getType(),
            'layout' => $dashboard->getLayout(),
            'widgets' => $dashboard->getWidgets(),
            'filters' => $dashboard->getFilters(),
            'is_default' => $dashboard->isDefault(),
            'is_public' => $dashboard->isPublic(),
            'agency' => $dashboard->getAgency() ? [
                'id' => $dashboard->getAgency()->getId(),
                'name' => $dashboard->getAgency()->getName(),
            ] : null,
            'created_at' => $dashboard->getCreatedAt()->format('c'),
            'updated_at' => $dashboard->getUpdatedAt()->format('c'),
        ];
    }
}
