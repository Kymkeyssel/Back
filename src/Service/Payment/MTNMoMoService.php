<?php

namespace App\Service\Payment;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class MTNMoMoService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $apiSecret,
        private string $subscriptionKey,
        private string $baseUrl = 'https://sandbox.momodeveloper.mtn.com'
    ) {
    }

    /**
     * Get access token
     */
    public function getAccessToken(): string
    {
        $response = $this->httpClient->request('POST', "{$this->baseUrl}/collection/token/", [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode("{$this->apiKey}:{$this->apiSecret}"),
                'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
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

        $response = $this->httpClient->request('POST', "{$this->baseUrl}/collection/v1_0/requesttopay", [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'X-Reference-Id' => $reference,
                'X-Target-Environment' => 'sandbox',
                'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'amount' => $amount,
                'currency' => 'XAF',
                'externalId' => $reference,
                'payer' => [
                    'partyIdType' => 'MSISDN',
                    'partyId' => $this->formatPhone($phone),
                ],
                'payerMessage' => 'Payment for TransCam booking',
                'payeeNote' => 'TransCam payment',
            ],
        ]);

        return [
            'success' => $response->getStatusCode() === 202,
            'reference' => $reference,
            'status' => 'pending',
        ];
    }

    /**
     * Check payment status
     */
    public function checkPaymentStatus(string $reference): array
    {
        $token = $this->getAccessToken();

        $response = $this->httpClient->request('GET', "{$this->baseUrl}/collection/v1_0/requesttopay/{$reference}", [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'X-Target-Environment' => 'sandbox',
                'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
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
            'reference' => $data['referenceId'] ?? null,
            'status' => $data['status'] ?? 'unknown',
            'providerReference' => $data['financialTransactionId'] ?? null,
        ];
    }

    /**
     * Refund payment
     */
    public function refundPayment(string $reference, float $amount): array
    {
        $token = $this->getAccessToken();

        $response = $this->httpClient->request('POST', "{$this->baseUrl}/collection/v1_0/requesttopay/{$reference}/refund", [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'X-Target-Environment' => 'sandbox',
                'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'amount' => $amount,
                'currency' => 'XAF',
                'externalId' => $reference . '-refund',
            ],
        ]);

        return [
            'success' => $response->getStatusCode() === 202,
            'reference' => $reference,
            'status' => 'refunded',
        ];
    }

    /**
     * Format phone number
     */
    private function formatPhone(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Add country code if missing
        if (strlen($phone) === 9) {
            $phone = '237' . $phone;
        }

        return $phone;
    }
}
