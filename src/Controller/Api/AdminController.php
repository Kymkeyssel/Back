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

#[Route('/api')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
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

    #[Route('/admin/overview', name: 'api_admin_overview', methods: ['GET'])]
    public function overview(): JsonResponse
    {
        $totalUsers = $this->userRepository->count([]);
        $totalAgencies = $this->agencyRepository->count([]);
        $totalTrips = $this->tripRepository->count([]);
        $totalBookings = $this->bookingRepository->count([]);
        $totalRevenue = $this->paymentRepository->totalCompletedAmount();

        return $this->json([
            'success' => true,
            'data' => [
                'users' => $totalUsers,
                'agencies' => $totalAgencies,
                'trips' => $totalTrips,
                'bookings' => $totalBookings,
                'revenue' => $totalRevenue,
                'pendingAgencies' => $this->agencyRepository->count(['isVerified' => false]),
                'activeTrips' => $this->tripRepository->count(['status' => 'scheduled']),
            ],
        ]);
    }

    #[Route('/users', name: 'api_admin_users', methods: ['GET'])]
    public function users(): JsonResponse
    {
        $users = $this->userRepository->findBy([], ['createdAt' => 'DESC'], 500);

        $data = array_map(fn (User $u) => [
            'id' => $u->getId(),
            'email' => $u->getEmail(),
            'firstName' => $u->getFirstName(),
            'lastName' => $u->getLastName(),
            'fullName' => $u->getFullName(),
            'phone' => $u->getPhone(),
            'roles' => $u->getRoles(),
            'isVerified' => $u->isVerified(),
            'createdAt' => $u->getCreatedAt()?->format('Y-m-d H:i:s'),
        ], $users);

        return $this->json(['success' => true, 'data' => $data]);
    }

    #[Route('/users/{id}/toggle-status', name: 'api_admin_user_toggle', methods: ['PUT'])]
    public function toggleUserStatus(int $id): JsonResponse
    {
        $target = $this->userRepository->find($id);
        if (!$target) {
            return $this->json(['success' => false, 'message' => 'User not found.'], Response::HTTP_NOT_FOUND);
        }

        /** @var User $actor */
        $actor = $this->getUser();
        if ($target === $actor) {
            return $this->json(['success' => false, 'message' => 'Cannot modify your own status.'], Response::HTTP_FORBIDDEN);
        }

        $target->setIsVerified(!$target->isVerified());
        $this->entityManager->flush();

        return $this->json(['success' => true, 'message' => 'User status updated.', 'data' => ['id' => $target->getId(), 'isVerified' => $target->isVerified()]]);
    }

    #[Route('/agencies/pending', name: 'api_admin_agencies_pending', methods: ['GET'])]
    public function pendingAgencies(): JsonResponse
    {
        $agencies = $this->agencyRepository->findBy(['isVerified' => false, 'isActive' => true], ['createdAt' => 'DESC']);

        $data = [];
        foreach ($agencies as $agency) {
            $data[] = [
                'id' => $agency->getId(),
                'name' => $agency->getName(),
                'city' => $agency->getCity(),
                'owner' => $agency->getOwner()?->getFullName(),
                'phone' => $agency->getPhone(),
                'createdAt' => $agency->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return $this->json(['success' => true, 'data' => $data]);
    }

    #[Route('/agencies/{id}/verify', name: 'api_admin_agency_verify', methods: ['PUT'])]
    public function verifyAgency(int $id): JsonResponse
    {
        $agency = $this->agencyRepository->find($id);
        if (!$agency) {
            return $this->json(['success' => false, 'message' => 'Agency not found.'], Response::HTTP_NOT_FOUND);
        }

        $agency->setIsVerified(true);
        $this->entityManager->flush();

        return $this->json(['success' => true, 'message' => 'Agency verified.']);
    }
}
