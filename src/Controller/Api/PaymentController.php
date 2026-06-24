<?php

namespace App\Controller\Api;

use App\Entity\Payment;
use App\Repository\BookingRepository;
use App\Repository\PaymentRepository;
use App\Service\Payment\NotchPayService;
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
        private ValidatorInterface $validator,
        private NotchPayService $notchPayService,
        private ?string $notchPayCallbackUrl = null,
        private ?string $notchPayHashKey = null
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

    #[Route('/api/payments/{id<\d+>}', name: 'api_payment_show', methods: ['GET'])]
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

        if ($payment->getUser() !== $user && !$this->isGranted('ROLE_ADMIN')) {
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

        $data = $this->getRequestData($request);
        if ($data === null) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid JSON payload.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $bookingId = (int) ($data['bookingId'] ?? 0);
        $method = (string) ($data['method'] ?? '');

        if ($bookingId <= 0) {
            return $this->json([
                'success' => false,
                'message' => 'BookingId is required.'
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!in_array($method, Payment::NOTCHPAY_METHODS, true)) {
            return $this->json([
                'success' => false,
                'message' => 'This payment method is not supported by NotchPay.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $currency = (string) ($data['currency'] ?? 'XAF');
        if ($currency !== 'XAF') {
            return $this->json([
                'success' => false,
                'message' => 'Only XAF currency is supported.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $paymentPhone = (string) ($data['phone'] ?? $data['paymentPhone'] ?? '');
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];

        $booking = $this->bookingRepository->find($bookingId);
        if (!$booking) {
            return $this->json([
                'success' => false,
                'message' => 'Booking not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        if ($booking->getUser() !== $user) {
            return $this->json([
                'success' => false,
                'message' => 'You do not have permission to pay for this booking.'
            ], Response::HTTP_FORBIDDEN);
        }

        if ($booking->getStatus() !== 'pending') {
            return $this->json([
                'success' => false,
                'message' => 'Booking is not in pending status.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $totalPrice = $booking->getTotalPrice();
        if ($totalPrice === null || !is_numeric($totalPrice) || (float) $totalPrice <= 0) {
            return $this->json([
                'success' => false,
                'message' => 'Booking total price is invalid.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $activePayment = $this->paymentRepository->findActiveByBooking($bookingId);
        if ($activePayment !== null) {
            return $this->json([
                'success' => true,
                'message' => 'Existing payment found for this booking.',
                'data' => $this->serializePayment($activePayment),
                'authorizationUrl' => $activePayment->getAuthorizationUrl(),
            ], Response::HTTP_OK);
        }

        $transactionId = $this->generateTransactionId();
        $callbackUrl = ($this->notchPayCallbackUrl ?: 'http://localhost:5173/payment/callback?reference=') . $transactionId;

        $payment = new Payment();
        $payment->setBooking($booking);
        $payment->setUser($user);
        $payment->setMethod($method);
        $payment->setAmount($totalPrice);
        $payment->setCurrency($currency);
        $payment->setStatus(Payment::STATUS_PROCESSING);
        $payment->setTransactionId($transactionId);
        $payment->setMetadata(array_replace($metadata, [
            'callback_url' => $callbackUrl,
            'payment_phone' => $paymentPhone ?: $user->getPhone(),
        ]));

        $errors = $this->validator->validate($payment);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }

            return $this->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $customerPhone = match ($method) {
                Payment::METHOD_ORANGE_MONEY => $paymentPhone ?: ($user->getOrangeMoneyPhone() ?? $user->getPhone()),
                Payment::METHOD_MTN_MOMO => $paymentPhone ?: ($user->getMtnMoMoPhone() ?? $user->getPhone()),
                default => $paymentPhone ?: $user->getPhone(),
            };

            $notchResponse = $this->notchPayService->createPayment(
                (float) $totalPrice,
                trim($user->getFirstName() . ' ' . $user->getLastName()),
                $user->getEmail(),
                $customerPhone,
                $transactionId,
                $callbackUrl
            );
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => 'Unable to initialize NotchPay payment.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_GATEWAY);
        }

        $authorizationUrl = (string) ($notchResponse['authorization_url'] ?? '');
        if ($authorizationUrl === '') {
            return $this->json([
                'success' => false,
                'message' => 'NotchPay did not return an authorization URL.'
            ], Response::HTTP_BAD_GATEWAY);
        }

        $payment->setProviderReference($notchResponse['provider_reference'] ?? $notchResponse['reference'] ?? null);
        $payment->setNotchPaymentReference($notchResponse['reference'] ?? $notchResponse['provider_reference'] ?? null);
        $payment->setAuthorizationUrl($authorizationUrl);
        $payment->setMetadata(array_replace($payment->getMetadata() ?? [], [
            'notchpay' => [
                'status_code' => $notchResponse['status_code'] ?? null,
                'reference' => $notchResponse['reference'] ?? null,
                'transaction' => $notchResponse['transaction'] ?? null,
            ],
        ]));

        try {
            $this->entityManager->persist($payment);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => 'Unable to save payment.',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'success' => true,
            'message' => 'Payment initialized.',
            'data' => $this->serializePayment($payment),
            'authorizationUrl' => $authorizationUrl,
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/payments/callback', name: 'api_payment_callback', methods: ['GET', 'POST'])]
    public function callback(Request $request): JsonResponse
    {
        $data = $this->getRequestData($request);
        if ($data === null) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid callback data.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $payment = $this->findPaymentForCallback($data);
        if (!$payment) {
            return $this->json([
                'success' => false,
                'message' => 'Payment not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        $providerReference = (string) ($data['reference'] ?? $data['providerReference'] ?? $data['provider_reference'] ?? $payment->getProviderReference() ?? '');
        if ($providerReference === '') {
            return $this->json([
                'success' => false,
                'message' => 'NotchPay reference is required.'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $notchResponse = $this->notchPayService->getPayment($providerReference);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => 'Unable to verify NotchPay payment.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_GATEWAY);
        }

        $status = (string) ($notchResponse['status'] ?? $this->notchPayService->normalizeStatus($data['status'] ?? null));
        if (!in_array($status, Payment::STATUSES, true)) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid payment status.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $oldStatus = $payment->getStatus();
        if ($oldStatus === Payment::STATUS_COMPLETED && $status !== Payment::STATUS_COMPLETED) {
            return $this->json([
                'success' => false,
                'message' => 'Completed payment cannot be downgraded.'
            ], Response::HTTP_CONFLICT);
        }

        $payment->setProviderReference($providerReference);
        $payment->setNotchPaymentReference($notchResponse['reference'] ?? $providerReference);
        $payment->setMetadata(array_replace($payment->getMetadata() ?? [], [
            'notchpay_callback' => $data,
            'notchpay_verification' => $notchResponse,
        ]));

        if ($status === Payment::STATUS_COMPLETED && $payment->getCompletedAt() === null) {
            $payment->setCompletedAt(new \DateTimeImmutable());
        }

        if ($oldStatus !== $status) {
            $payment->setStatus($status);
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Payment callback processed successfully.',
            'data' => $this->serializePayment($payment)
        ]);
    }

    #[Route('/api/agencies/{id}/payments', name: 'api_agency_payments_list', methods: ['GET'])]
    public function agencyPayments(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['success' => false, 'message' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }

        $agency = $this->entityManager->getRepository(\App\Entity\Agency::class)->find($id);
        if (!$agency) {
            return $this->json(['success' => false, 'message' => 'Agency not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($agency->getOwner() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['success' => false, 'message' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $payments = $this->paymentRepository->findByAgency($id);

        $data = [];
        foreach ($payments as $payment) {
            $data[] = $this->serializePayment($payment);
        }

        return $this->json(['success' => true, 'data' => $data]);
    }

    #[Route('/api/webhooks/notchpay', name: 'api_webhooks_notchpay', methods: ['POST'])]
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = (string) ($request->headers->get('x-notch-signature') ?? '');

        $expectedSignature = $this->notchPayHashKey !== null && $this->notchPayHashKey !== ''
            ? hash_hmac('sha256', $payload, $this->notchPayHashKey)
            : '';

        if ($signature === '' || !hash_equals($expectedSignature, $signature)) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid webhook signature.'
            ], Response::HTTP_FORBIDDEN);
        }

        $event = json_decode($payload, true);
        if (!is_array($event) || empty($event['type']) || empty($event['data']['reference'])) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid webhook payload.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $eventType = (string) $event['type'];
        $providerReference = (string) $event['data']['reference'];

        if (!str_starts_with($eventType, 'payment.')) {
            return $this->json([
                'success' => true,
                'message' => 'Event ignored.'
            ], Response::HTTP_OK);
        }

        $payment = $this->paymentRepository->findByProviderReference($providerReference)
            ?? $this->paymentRepository->findByNotchPaymentReference($providerReference);

        if ($payment === null) {
            return $this->json([
                'success' => false,
                'message' => 'Payment not found for webhook event.'
            ], Response::HTTP_NOT_FOUND);
        }

        $oldStatus = $payment->getStatus();
        $notchStatus = (string) ($event['data']['status'] ?? $event['data']['transaction']['status'] ?? '');
        $newStatus = $this->notchPayService->normalizeStatus($notchStatus);

        if (!in_array($newStatus, Payment::STATUSES, true)) {
            $newStatus = $oldStatus;
        }

        if ($oldStatus === Payment::STATUS_COMPLETED && $newStatus !== Payment::STATUS_COMPLETED) {
            return $this->json([
                'success' => true,
                'message' => 'Completed payment not downgraded.'
            ], Response::HTTP_OK);
        }

        $payment->setStatus($newStatus);
        $payment->setProviderReference($providerReference);
        $payment->setNotchPaymentReference($event['data']['reference'] ?? $providerReference);
        $payment->setMetadata(array_replace($payment->getMetadata() ?? [], [
            'notchpay_webhook' => [
                'type' => $eventType,
                'received_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'data' => $event['data'] ?? [],
            ],
        ]));

        if ($newStatus === Payment::STATUS_COMPLETED && $payment->getCompletedAt() === null) {
            $payment->setCompletedAt(new \DateTimeImmutable());
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Webhook processed.',
            'data' => $this->serializePayment($payment)
        ]);
    }

    private function getRequestData(Request $request): ?array
    {
        if ($request->isMethod('POST')) {
            $data = json_decode($request->getContent(), true);

            return is_array($data) ? $data : $request->request->all();
        }

        return $request->query->all();
    }

    private function findPaymentForCallback(array $data): ?Payment
    {
        $merchantReference = (string) ($data['trxref'] ?? $data['transactionId'] ?? $data['transaction_id'] ?? '');
        if ($merchantReference !== '') {
            $payment = $this->paymentRepository->findByTransactionId($merchantReference);
            if ($payment !== null) {
                return $payment;
            }
        }

        $providerReference = (string) ($data['reference'] ?? $data['providerReference'] ?? $data['provider_reference'] ?? '');
        if ($providerReference !== '') {
            $payment = $this->paymentRepository->findByProviderReference($providerReference);
            if ($payment !== null) {
                return $payment;
            }

            return $this->paymentRepository->findByNotchPaymentReference($providerReference);
        }

        return null;
    }

    private function generateTransactionId(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $transactionId = 'TXN-' . strtoupper(bin2hex(random_bytes(8)));
            if ($this->paymentRepository->findByTransactionId($transactionId) === null) {
                return $transactionId;
            }
        }

        throw new \RuntimeException('Unable to generate a unique transaction ID.');
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
            'notchPaymentReference' => $payment->getNotchPaymentReference(),
            'authorizationUrl' => $payment->getAuthorizationUrl(),
            'metadata' => $payment->getMetadata(),
            'createdAt' => $payment->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $payment->getUpdatedAt()->format('Y-m-d H:i:s'),
            'completedAt' => $payment->getCompletedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
