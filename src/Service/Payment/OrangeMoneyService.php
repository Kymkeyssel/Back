<?php

namespace App\Service\Payment;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class OrangeMoneyService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $apiSecret,
        private string $merchantKey,
        private string $baseUrl = 'https://api.orange.com'
    ) {
    }

    /**
     * Get access token
     */
    public function getAccessToken(): string
    {
        $response = $this->httpClient->request('POST', "{$this->baseUrl}/oauth/v3/token", [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode("{$this->apiKey}:{$this->apiSecret}"),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'grant_type' => 'client_credentials',
            ],
        ]);

        $data = $response->toArray();

        return $data['access_token'];
    }

    /**
     * Initiate payment
     */
    public function initiatePayment(float $amount, string $phone, string $reference): array
    {
        $token = $this->getAccessToken();

        $response = $this->httpClient->request('POST', "{$this->baseUrl}/omcoreapis/1.0.2/mp/pay", [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'merchant_key' => $this->merchantKey,
                'currency' => 'XAF',
                'order_id' => $reference,
                'amount' => $amount,
                'return_url' => 'https://transcam.com/payment/callback',
                'cancel_url' => 'https://transcam.com/payment/cancel',
                'notif_url' => 'https://transcam.com/api/payments/callback',
                'lang' => 'fr',
                'reference' => $reference,
            ],
        ]);

        $data = $response->toArray();

        return [
            'success' => isset($data['payment_url']),
            'reference' => $reference,
            'payment_url' => $data['payment_url'] ?? null,
            'status' => 'pending',
        ];
    }

    /**
     * Check payment status
     */
    public function checkPaymentStatus(string $reference): array
    {
        $token = $this->getAccessToken();

        $response = $this->httpClient->request('GET', "{$this->baseUrl}/omcoreapis/1.0.2/mp/paystatus", [
            'headers' => [
                'Authorization' => "Bearer {$token}",
            ],
            'query' => [
                'order_id' => $reference,
                'merchant_key' => $this->merchantKey,
            ],
        ]);

        $data = $response->toArray();

        return [
            'status' => $data['status'] ?? 'unknown',
            'reference' => $reference,
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? null,
        ];
    }

    /**
     * Process callback
     */
    public function processCallback(array $data): array
    {
        return [
            'success' => true,
            'reference' => $data['order_id'] ?? null,
            'status' => $data['status'] ?? 'unknown',
            'providerReference' => $data['txnid'] ?? null,
        ];
    }

    /**
     * Refund payment
     */
    public function refundPayment(string $reference, float $amount): array
    {
        $token = $this->getAccessToken();

        $response = $this->httpClient->request('POST', "{$this->baseUrl}/omcoreapis/1.0.2/mp/refund", [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'merchant_key' => $this->merchantKey,
                'order_id' => $reference,
                'amount' => $amount,
                'currency' => 'XAF',
            ],
        ]);

        $data = $response->toArray();

        return [
            'success' => isset($data['status']) && $data['status'] === 'SUCCESS',
            'reference' => $reference,
            'status' => 'refunded',
        ];
    }
}
