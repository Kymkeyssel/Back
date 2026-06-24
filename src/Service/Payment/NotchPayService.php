<?php

namespace App\Service\Payment;

use App\Entity\Payment;
use JsonException;
use RuntimeException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class NotchPayService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $publicKey,
        private ?string $hashKey = null,
        private string $baseUrl = 'https://api.notchpay.co'
    ) {
    }

    public function createPayment(
        float $amount,
        string $customerName,
        ?string $customerEmail,
        ?string $customerPhone,
        string $reference,
        string $callbackUrl,
        string $description = 'One4All Connect booking payment'
    ): array {
        if ($this->publicKey === '') {
            throw new RuntimeException('NotchPay public key is not configured.');
        }

        if ($amount <= 0) {
            throw new RuntimeException('Payment amount must be positive.');
        }

        if (trim($customerName) === '') {
            throw new RuntimeException('Customer name is required.');
        }

        if ($customerEmail === null && $customerPhone === null) {
            throw new RuntimeException('Customer email or phone is required.');
        }

        if ($reference === '') {
            throw new RuntimeException('Payment reference is required.');
        }

        if (!filter_var($callbackUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Payment callback URL is invalid.');
        }

        $payload = [
            'amount' => $amount,
            'currency' => 'XAF',
            'customer' => [
                'name' => trim($customerName),
                'email' => $customerEmail,
                'phone' => $customerPhone,
            ],
            'description' => $description,
            'callback' => $callbackUrl,
            'reference' => $reference,
        ];

        $payload['customer'] = array_filter(
            $payload['customer'],
            static fn (?string $value): bool => $value !== null && $value !== ''
        );

        $response = $this->requestJson('POST', $this->baseUrl . '/payments', [
            'headers' => [
                'Authorization' => $this->publicKey,
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);

        $data = $response['content'];
        $transaction = $this->normalizeTransaction($data['transaction'] ?? null);
        $notchReference = (string) ($transaction['reference'] ?? $data['reference'] ?? $reference);
        $authorizationUrl = (string) ($data['authorization_url'] ?? $data['authorizationUrl'] ?? '');

        return [
            'success' => true,
            'status_code' => $response['status_code'],
            'data' => $data,
            'transaction' => $transaction,
            'reference' => $notchReference,
            'authorization_url' => $authorizationUrl,
            'provider_reference' => $notchReference,
            'status' => $this->normalizeStatus($data['status'] ?? $transaction['status'] ?? Payment::STATUS_PROCESSING),
        ];
    }

    public function getPayment(string $reference): array
    {
        if ($this->publicKey === '') {
            throw new RuntimeException('NotchPay public key is not configured.');
        }

        if ($reference === '') {
            throw new RuntimeException('NotchPay reference is required.');
        }

        $response = $this->requestJson('GET', $this->baseUrl . '/payments/' . rawurlencode($reference), [
            'headers' => [
                'Authorization' => $this->publicKey,
                'Accept' => 'application/json',
            ],
        ]);

        $data = $response['content'];
        $transaction = $this->normalizeTransaction($data['transaction'] ?? null);
        $notchReference = (string) ($transaction['reference'] ?? $data['reference'] ?? $reference);

        return [
            'success' => true,
            'status_code' => $response['status_code'],
            'data' => $data,
            'transaction' => $transaction,
            'reference' => $notchReference,
            'provider_reference' => $notchReference,
            'status' => $this->normalizeStatus($transaction['status'] ?? $data['status'] ?? null),
        ];
    }

    public function normalizeStatus(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'complete', 'completed', 'success', 'successful', 'paid', 'paid_out' => Payment::STATUS_COMPLETED,
            'failed', 'failure', 'declined', 'rejected', 'cancelled', 'canceled' => Payment::STATUS_FAILED,
            'refunded' => Payment::STATUS_REFUNDED,
            'pending', 'processing', 'initiated', 'accepted', 'authorized' => Payment::STATUS_PROCESSING,
            default => Payment::STATUS_PENDING,
        };
    }

    private function requestJson(string $method, string $url, array $options = []): array
    {
        $options['timeout'] = $options['timeout'] ?? 30;

        try {
            $response = $this->httpClient->request($method, $url, $options);
            $statusCode = $response->getStatusCode();
            $content = $response->toArray(false);
        } catch (ClientExceptionInterface|TransportExceptionInterface|JsonException $e) {
            throw new RuntimeException('NotchPay request failed: ' . $e->getMessage(), 0, $e);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException('NotchPay request failed with HTTP ' . $statusCode . ': ' . json_encode($content));
        }

        return [
            'status_code' => $statusCode,
            'content' => $content,
        ];
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        if ($this->hashKey === null || $this->hashKey === '') {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $this->hashKey);

        return hash_equals($expectedSignature, $signature);
    }

    private function normalizeTransaction(mixed $transaction): array
    {
        if (is_array($transaction)) {
            return $transaction;
        }

        if (is_string($transaction) && $transaction !== '') {
            return ['id' => $transaction];
        }

        return [];
    }
}
