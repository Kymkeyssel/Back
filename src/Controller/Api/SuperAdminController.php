<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\AgencyRepository;
use App\Repository\BookingRepository;
use App\Repository\PaymentRepository;
use App\Repository\TripRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/super-admin')]
#[IsGranted('ROLE_SUPER_ADMIN')]
class SuperAdminController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private AgencyRepository $agencyRepository,
        private TripRepository $tripRepository,
        private BookingRepository $bookingRepository,
        private PaymentRepository $paymentRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/overview', name: 'api_super_admin_overview', methods: ['GET'])]
    public function overview(): JsonResponse
    {
        return $this->json([
            'success' => true,
            'data' => [
                'users' => $this->userRepository->count([]),
                'agencies' => $this->agencyRepository->count([]),
                'trips' => $this->tripRepository->count([]),
                'bookings' => $this->bookingRepository->count([]),
                'payments' => $this->paymentRepository->count([]),
            ],
        ]);
    }

    #[Route('/users', name: 'api_super_admin_users', methods: ['GET'])]
    public function users(): JsonResponse
    {
        $users = $this->userRepository->findBy([], ['createdAt' => 'DESC'], 500);

        $data = array_map(fn (User $u) => $this->serializeUser($u), $users);

        return $this->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    #[Route('/users/{id}', name: 'api_super_admin_user_update', methods: ['PUT'])]
    public function updateUser(int $id, Request $request): JsonResponse
    {
        $target = $this->userRepository->find($id);
        if (!$target) {
            return $this->json(['success' => false, 'message' => 'User not found.'], Response::HTTP_NOT_FOUND);
        }

        /** @var User $actor */
        $actor = $this->getUser();
        if ($target === $actor) {
            return $this->json(['success' => false, 'message' => 'Cannot modify your own roles via this endpoint.'], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode($request->getContent(), true) ?? [];

        if (isset($payload['roles']) && is_array($payload['roles'])) {
            $allowed = ['ROLE_USER', 'ROLE_AGENCY', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'];
            $roles = array_values(array_unique(array_filter(
                $payload['roles'],
                fn ($r) => is_string($r) && in_array($r, $allowed, true)
            )));
            if ($roles === []) {
                $roles = ['ROLE_USER'];
            }
            if (!in_array('ROLE_USER', $roles, true)) {
                $roles[] = 'ROLE_USER';
            }
            $target->setRoles($roles);
        }

        if (array_key_exists('isVerified', $payload)) {
            $target->setIsVerified((bool) $payload['isVerified']);
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'User updated.',
            'data' => $this->serializeUser($target),
        ]);
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'fullName' => trim($user->getFirstName() . ' ' . $user->getLastName()),
            'phone' => $user->getPhone(),
            'roles' => $user->getRoles(),
            'isVerified' => $user->getIsVerified(),
            'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
