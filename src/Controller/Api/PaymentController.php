<?php

namespace App\Controller\Api;

use App\Entity\Payment;
use App\Repository\BookingRepository;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PaymentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PaymentRepository $paymentRepository,
        private BookingRepository $bookingRepository,
        private ValidatorInterface $validator
    ) {
    }

    #[Route('/api/payments', name: 'api_payments', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Authentication required.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $payments = $this->paymentRepository->findByUser($user->getId());

        $data = [];
        foreach ($payments as $payment) {
            $data[] = $this->serializePayment($payment);
        }

        return $this->json([
            'success' => true,
            'data' => $data
        ]);
    }

    #[Route('/api/payments/{id}', name: 'api_payment_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Authentication required.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $payment = $this->paymentRepository->find($id);

        if (!$payment) {
            return $this->json([
                'success' => false,
                'message' => 'Payment not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($payment->getUser() !== $user && !in_array('ROLE_ADMIN', $user->getRoles())) {
            return $this->json([
                'success' => false,
                'message' => 'You do not have permission to view this payment.'
            ], Response::HTTP_FORBIDDEN);
        }

        return $this->json([
            'success' => true,
            'data' => $this->serializePayment($payment)
        ]);
    }

    #[Route('/api/payments', name: 'api_payment_create', methods: ['POST'])]
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
        $requiredFields = ['bookingId', 'method'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return $this->json([
                    'success' => false,
                    'message' => ucfirst($field) . ' is required.'
                ], Response::HTTP_BAD_REQUEST);
            }
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
                'message' => 'You do not have permission to pay for this booking.'
            ], Response::HTTP_FORBIDDEN);
        }

        // Check if booking is pending
        if ($booking->getStatus() !== 'pending') {
            return $this->json([
                'success' => false,
                'message' => 'Booking is not in pending status.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Generate transaction ID
        $transactionId = 'TXN-' . strtoupper(substr(md5(uniqid()), 0, 12));

        // Create payment
        $payment = new Payment();
        $payment->setBooking($booking);
        $payment->setUser($user);
        $payment->setMethod($data['method']);
        $payment->setAmount($booking->getTotalPrice());
        $payment->setCurrency($data['currency'] ?? 'XAF');
        $payment->setTransactionId($transactionId);
        $payment->setMetadata($data['metadata'] ?? null);

        // Validate
        $errors = $this->validator->validate($payment);
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
        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Payment initiated successfully.',
            'data' => $this->serializePayment($payment)
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/payments/callback', name: 'api_payment_callback', methods: ['POST'])]
    public function callback(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate callback data
        if (!isset($data['transactionId']) || !isset($data['status'])) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid callback data.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Find payment
        $payment = $this->paymentRepository->findByTransactionId($data['transactionId']);
        if (!$payment) {
            return $this->json([
                'success' => false,
                'message' => 'Payment not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        // Update payment status
        $payment->setStatus($data['status']);
        $payment->setProviderReference($data['providerReference'] ?? null);

        if ($data['status'] === 'completed') {
            $payment->setCompletedAt(new \DateTimeImmutable());

            // Update booking status
            $booking = $payment->getBooking();
            $booking->setStatus('confirmed');
        }

        // Save
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Payment callback processed successfully.'
        ]);
    }

    private function serializePayment(Payment $payment): array
    {
        return [
            'id' => $payment->getId(),
            'booking' => [
                'id' => $payment->getBooking()->getId(),
                'reference' => $payment->getBooking()->getReference(),
            ],
            'user' => [
                'id' => $payment->getUser()->getId(),
                'name' => $payment->getUser()->getFullName(),
            ],
            'method' => $payment->getMethod(),
            'amount' => $payment->getAmount(),
            'currency' => $payment->getCurrency(),
            'status' => $payment->getStatus(),
            'transactionId' => $payment->getTransactionId(),
            'providerReference' => $payment->getProviderReference(),
            'metadata' => $payment->getMetadata(),
            'createdAt' => $payment->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $payment->getUpdatedAt()->format('Y-m-d H:i:s'),
            'completedAt' => $payment->getCompletedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
