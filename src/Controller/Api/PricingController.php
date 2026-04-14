<?php

namespace App\Controller\Api;

use App\Entity\Agency;
use App\Entity\PricingRule;
use App\Entity\Trip;
use App\Repository\PricingRuleRepository;
use App\Service\PricingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/pricing')]
class PricingController extends AbstractController
{
    public function __construct(
        private PricingService $pricingService,
        private PricingRuleRepository $pricingRuleRepository,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator
    ) {
    }

    /**
     * Calculate price for a trip
     */
    #[Route('/calculate/{id}', name: 'api_pricing_calculate', methods: ['GET'])]
    public function calculatePrice(int $id, Request $request): JsonResponse
    {
        $trip = $this->entityManager->getRepository(Trip::class)->find($id);

        if (!$trip) {
            return $this->json([
                'success' => false,
                'message' => 'Trip not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $context = [];
        if ($request->query->has('user_id')) {
            $context['user_id'] = $request->query->getInt('user_id');
        }

        $priceCalculation = $this->pricingService->getPriceWithHistory($trip, $context);

        return $this->json([
            'success' => true,
            'data' => $priceCalculation,
        ]);
    }

    /**
     * Get pricing rules for an agency
     */
    #[Route('/rules/agency/{agencyId}', name: 'api_pricing_rules_by_agency', methods: ['GET'])]
    #[IsGranted('ROLE_AGENCY')]
    public function getRulesByAgency(int $agencyId): JsonResponse
    {
        $agency = $this->entityManager->getRepository(Agency::class)->find($agencyId);

        if (!$agency) {
            return $this->json([
                'success' => false,
                'message' => 'Agency not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Check if user owns this agency
        if ($agency->getOwner() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json([
                'success' => false,
                'message' => 'Access denied.',
            ], Response::HTTP_FORBIDDEN);
        }

        $rules = $this->pricingRuleRepository->findActiveByAgency($agency);

        return $this->json([
            'success' => true,
            'data' => array_map(fn(PricingRule $rule) => $this->serializeRule($rule), $rules),
        ]);
    }

    /**
     * Get all pricing rules (admin only)
     */
    #[Route('/rules', name: 'api_pricing_rules_all', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getAllRules(): JsonResponse
    {
        $rules = $this->pricingRuleRepository->findAllActive();

        return $this->json([
            'success' => true,
            'data' => array_map(fn(PricingRule $rule) => $this->serializeRule($rule), $rules),
        ]);
    }

    /**
     * Create a new pricing rule
     */
    #[Route('/rules', name: 'api_pricing_rule_create', methods: ['POST'])]
    #[IsGranted('ROLE_AGENCY')]
    public function createRule(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate required fields
        $requiredFields = ['name', 'type', 'multiplier'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return $this->json([
                    'success' => false,
                    'message' => sprintf('%s is required.', ucfirst($field)),
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Validate multiplier
        if (!is_numeric($data['multiplier']) || $data['multiplier'] <= 0) {
            return $this->json([
                'success' => false,
                'message' => 'Multiplier must be a positive number.',
            ], Response::HTTP_BAD_REQUEST);
        }

        // If agency_id is provided, check ownership
        if (isset($data['agency_id'])) {
            $agency = $this->entityManager->getRepository(Agency::class)->find($data['agency_id']);
            if (!$agency) {
                return $this->json([
                    'success' => false,
                    'message' => 'Agency not found.',
                ], Response::HTTP_NOT_FOUND);
            }

            if ($agency->getOwner() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
                return $this->json([
                    'success' => false,
                    'message' => 'Access denied.',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        try {
            $rule = $this->pricingService->createPricingRule($data);

            return $this->json([
                'success' => true,
                'message' => 'Pricing rule created successfully.',
                'data' => $this->serializeRule($rule),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to create pricing rule.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update a pricing rule
     */
    #[Route('/rules/{id}', name: 'api_pricing_rule_update', methods: ['PUT'])]
    #[IsGranted('ROLE_AGENCY')]
    public function updateRule(int $id, Request $request): JsonResponse
    {
        $rule = $this->pricingRuleRepository->find($id);

        if (!$rule) {
            return $this->json([
                'success' => false,
                'message' => 'Pricing rule not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($rule->getAgency() && $rule->getAgency()->getOwner() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json([
                'success' => false,
                'message' => 'Access denied.',
            ], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        try {
            $rule = $this->pricingService->updatePricingRule($rule, $data);

            return $this->json([
                'success' => true,
                'message' => 'Pricing rule updated successfully.',
                'data' => $this->serializeRule($rule),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to update pricing rule.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a pricing rule
     */
    #[Route('/rules/{id}', name: 'api_pricing_rule_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_AGENCY')]
    public function deleteRule(int $id): JsonResponse
    {
        $rule = $this->pricingRuleRepository->find($id);

        if (!$rule) {
            return $this->json([
                'success' => false,
                'message' => 'Pricing rule not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($rule->getAgency() && $rule->getAgency()->getOwner() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json([
                'success' => false,
                'message' => 'Access denied.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $rule->setIsActive(false);
            $this->pricingRuleRepository->save($rule, true);

            return $this->json([
                'success' => true,
                'message' => 'Pricing rule deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to delete pricing rule.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get pricing statistics for an agency
     */
    #[Route('/statistics/agency/{agencyId}', name: 'api_pricing_statistics', methods: ['GET'])]
    #[IsGranted('ROLE_AGENCY')]
    public function getStatistics(int $agencyId): JsonResponse
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

        $statistics = $this->pricingService->getPricingStatistics($agency);

        return $this->json([
            'success' => true,
            'data' => $statistics,
        ]);
    }

    /**
     * Get pricing rule types
     */
    #[Route('/rule-types', name: 'api_pricing_rule_types', methods: ['GET'])]
    public function getRuleTypes(): JsonResponse
    {
        $types = [
            ['value' => 'demand', 'label' => 'Demand-based', 'description' => 'Adjust price based on demand'],
            ['value' => 'time', 'label' => 'Time-based', 'description' => 'Adjust price based on time of booking'],
            ['value' => 'weather', 'label' => 'Weather-based', 'description' => 'Adjust price based on weather conditions'],
            ['value' => 'event', 'label' => 'Event-based', 'description' => 'Adjust price for special events'],
            ['value' => 'loyalty', 'label' => 'Loyalty', 'description' => 'Discount for loyal customers'],
            ['value' => 'early_bird', 'label' => 'Early Bird', 'description' => 'Discount for early bookings'],
            ['value' => 'last_minute', 'label' => 'Last Minute', 'description' => 'Premium for last-minute bookings'],
        ];

        return $this->json([
            'success' => true,
            'data' => $types,
        ]);
    }

    /**
     * Serialize pricing rule
     */
    private function serializeRule(PricingRule $rule): array
    {
        return [
            'id' => $rule->getId(),
            'name' => $rule->getName(),
            'type' => $rule->getType(),
            'conditions' => $rule->getConditions(),
            'multiplier' => $rule->getMultiplier(),
            'min_price' => $rule->getMinPrice(),
            'max_price' => $rule->getMaxPrice(),
            'priority' => $rule->getPriority(),
            'is_active' => $rule->isActive(),
            'starts_at' => $rule->getStartsAt()?->format('c'),
            'ends_at' => $rule->getEndsAt()?->format('c'),
            'agency' => $rule->getAgency() ? [
                'id' => $rule->getAgency()->getId(),
                'name' => $rule->getAgency()->getName(),
            ] : null,
            'created_at' => $rule->getCreatedAt()->format('c'),
            'updated_at' => $rule->getUpdatedAt()->format('c'),
        ];
    }
}
