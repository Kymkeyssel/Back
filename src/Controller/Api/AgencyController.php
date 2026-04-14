<?php

namespace App\Controller\Api;

use App\Entity\Agency;
use App\Repository\AgencyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AgencyController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AgencyRepository $agencyRepository,
        private ValidatorInterface $validator,
        private SluggerInterface $slugger
    ) {
    }

    #[Route('/api/agencies', name: 'api_agencies', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 20);
        $city = $request->query->get('city');
        $search = $request->query->get('search');

        if ($search) {
            $agencies = $this->agencyRepository->searchAgencies($search);
        } elseif ($city) {
            $agencies = $this->agencyRepository->findByCity($city);
        } else {
            $agencies = $this->agencyRepository->findActiveAgencies();
        }

        $data = [];
        foreach ($agencies as $agency) {
            $data[] = $this->serializeAgency($agency);
        }

        return $this->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => count($data)
            ]
        ]);
    }

    #[Route('/api/agencies/{id}', name: 'api_agency_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $agency = $this->agencyRepository->find($id);

        if (!$agency) {
            return $this->json([
                'success' => false,
                'message' => 'Agency not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => $this->serializeAgency($agency)
        ]);
    }

    #[Route('/api/agencies', name: 'api_agency_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Authentication required.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        // Validate required fields
        $requiredFields = ['name', 'phone', 'email', 'address', 'city'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return $this->json([
                    'success' => false,
                    'message' => ucfirst($field) . ' is required.'
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Create agency
        $agency = new Agency();
        $agency->setName($data['name']);
        $agency->setSlug($this->slugger->slug($data['name'])->lower());
        $agency->setDescription($data['description'] ?? null);
        $agency->setPhone($data['phone']);
        $agency->setEmail($data['email']);
        $agency->setAddress($data['address']);
        $agency->setCity($data['city']);
        $agency->setCountry($data['country'] ?? 'Cameroon');
        $agency->setLatitude($data['latitude'] ?? null);
        $agency->setLongitude($data['longitude'] ?? null);
        $agency->setOwner($user);

        // Validate
        $errors = $this->validator->validate($agency);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        // Save
        $this->entityManager->persist($agency);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Agency created successfully.',
            'data' => $this->serializeAgency($agency)
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/agencies/{id}', name: 'api_agency_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Authentication required.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $agency = $this->agencyRepository->find($id);

        if (!$agency) {
            return $this->json([
                'success' => false,
                'message' => 'Agency not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($agency->getOwner() !== $user && !in_array('ROLE_ADMIN', $user->getRoles())) {
            return $this->json([
                'success' => false,
                'message' => 'You do not have permission to update this agency.'
            ], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        // Update fields
        if (isset($data['name'])) {
            $agency->setName($data['name']);
            $agency->setSlug($this->slugger->slug($data['name'])->lower());
        }
        if (isset($data['description'])) {
            $agency->setDescription($data['description']);
        }
        if (isset($data['phone'])) {
            $agency->setPhone($data['phone']);
        }
        if (isset($data['email'])) {
            $agency->setEmail($data['email']);
        }
        if (isset($data['address'])) {
            $agency->setAddress($data['address']);
        }
        if (isset($data['city'])) {
            $agency->setCity($data['city']);
        }
        if (isset($data['country'])) {
            $agency->setCountry($data['country']);
        }
        if (isset($data['latitude'])) {
            $agency->setLatitude($data['latitude']);
        }
        if (isset($data['longitude'])) {
            $agency->setLongitude($data['longitude']);
        }

        // Validate
        $errors = $this->validator->validate($agency);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        // Save
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Agency updated successfully.',
            'data' => $this->serializeAgency($agency)
        ]);
    }

    #[Route('/api/agencies/{id}', name: 'api_agency_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Authentication required.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $agency = $this->agencyRepository->find($id);

        if (!$agency) {
            return $this->json([
                'success' => false,
                'message' => 'Agency not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($agency->getOwner() !== $user && !in_array('ROLE_ADMIN', $user->getRoles())) {
            return $this->json([
                'success' => false,
                'message' => 'You do not have permission to delete this agency.'
            ], Response::HTTP_FORBIDDEN);
        }

        // Soft delete (set inactive)
        $agency->setIsActive(false);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Agency deleted successfully.'
        ]);
    }

    private function serializeAgency(Agency $agency): array
    {
        return [
            'id' => $agency->getId(),
            'name' => $agency->getName(),
            'slug' => $agency->getSlug(),
            'description' => $agency->getDescription(),
            'logo' => $agency->getLogo(),
            'coverImage' => $agency->getCoverImage(),
            'phone' => $agency->getPhone(),
            'email' => $agency->getEmail(),
            'address' => $agency->getAddress(),
            'city' => $agency->getCity(),
            'country' => $agency->getCountry(),
            'latitude' => $agency->getLatitude(),
            'longitude' => $agency->getLongitude(),
            'rating' => $agency->getRating(),
            'totalReviews' => $agency->getTotalReviews(),
            'isVerified' => $agency->isVerified(),
            'isActive' => $agency->isActive(),
            'owner' => [
                'id' => $agency->getOwner()->getId(),
                'name' => $agency->getOwner()->getFullName(),
            ],
            'createdAt' => $agency->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $agency->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];
    }
}
