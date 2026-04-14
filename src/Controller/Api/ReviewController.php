<?php

namespace App\Controller\Api;

use App\Entity\Review;
use App\Repository\AgencyRepository;
use App\Repository\BookingRepository;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ReviewController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ReviewRepository $reviewRepository,
        private AgencyRepository $agencyRepository,
        private BookingRepository $bookingRepository,
        private ValidatorInterface $validator
    ) {
    }

    #[Route('/api/reviews', name: 'api_reviews', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Authentication required.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $reviews = $this->reviewRepository->findByUser($user->getId());

        $data = [];
        foreach ($reviews as $review) {
            $data[] = $this->serializeReview($review);
        }

        return $this->json([
            'success' => true,
            'data' => $data
        ]);
    }

    #[Route('/api/reviews/agency/{id}', name: 'api_reviews_agency', methods: ['GET'])]
    public function byAgency(int $id): JsonResponse
    {
        $agency = $this->agencyRepository->find($id);

        if (!$agency) {
            return $this->json([
                'success' => false,
                'message' => 'Agency not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        $reviews = $this->reviewRepository->findByAgency($id);

        $data = [];
        foreach ($reviews as $review) {
            $data[] = $this->serializeReview($review);
        }

        return $this->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'agencyId' => $id,
                'averageRating' => $this->reviewRepository->getAverageRatingByAgency($id),
                'totalReviews' => $this->reviewRepository->countReviewsByAgency($id),
            ]
        ]);
    }

    #[Route('/api/reviews', name: 'api_review_create', methods: ['POST'])]
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
        $requiredFields = ['agencyId', 'bookingId', 'rating'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return $this->json([
                    'success' => false,
                    'message' => ucfirst($field) . ' is required.'
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Get agency
        $agency = $this->agencyRepository->find($data['agencyId']);
        if (!$agency) {
            return $this->json([
                'success' => false,
                'message' => 'Agency not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        // Get booking
        $booking = $this->bookingRepository->find($data['bookingId']);
        if (!$booking) {
            return $this->json([
                'success' => false,
                'message' => 'Booking not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($booking->getUser() !== $user) {
            return $this->json([
                'success' => false,
                'message' => 'You do not have permission to review this booking.'
            ], Response::HTTP_FORBIDDEN);
        }

        // Check if booking is completed
        if ($booking->getStatus() !== 'completed') {
            return $this->json([
                'success' => false,
                'message' => 'You can only review completed bookings.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check if already reviewed
        $existingReview = $this->reviewRepository->findOneBy(['booking' => $booking]);
        if ($existingReview) {
            return $this->json([
                'success' => false,
                'message' => 'You have already reviewed this booking.'
            ], Response::HTTP_CONFLICT);
        }

        // Create review
        $review = new Review();
        $review->setUser($user);
        $review->setAgency($agency);
        $review->setBooking($booking);
        $review->setRating($data['rating']);
        $review->setComment($data['comment'] ?? null);

        // Validate
        $errors = $this->validator->validate($review);
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
        $this->entityManager->persist($review);
        $this->entityManager->flush();

        // Update agency rating
        $this->updateAgencyRating($agency->getId());

        return $this->json([
            'success' => true,
            'message' => 'Review created successfully.',
            'data' => $this->serializeReview($review)
        ], Response::HTTP_CREATED);
    }

    private function updateAgencyRating(int $agencyId): void
    {
        $agency = $this->agencyRepository->find($agencyId);
        if ($agency) {
            $averageRating = $this->reviewRepository->getAverageRatingByAgency($agencyId);
            $totalReviews = $this->reviewRepository->countReviewsByAgency($agencyId);

            $agency->setRating($averageRating);
            $agency->setTotalReviews($totalReviews);

            $this->entityManager->flush();
        }
    }

    private function serializeReview(Review $review): array
    {
        return [
            'id' => $review->getId(),
            'user' => [
                'id' => $review->getUser()->getId(),
                'name' => $review->getUser()->getFullName(),
            ],
            'agency' => [
                'id' => $review->getAgency()->getId(),
                'name' => $review->getAgency()->getName(),
            ],
            'booking' => [
                'id' => $review->getBooking()->getId(),
                'reference' => $review->getBooking()->getReference(),
            ],
            'rating' => $review->getRating(),
            'comment' => $review->getComment(),
            'isVerified' => $review->isVerified(),
            'createdAt' => $review->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $review->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];
    }
}
