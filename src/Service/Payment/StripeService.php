<?php

namespace App\Service\Payment;

use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Refund;

class StripeService
{
    public function __construct(
        private string $secretKey,
        private string $publicKey
    ) {
        Stripe::setApiKey($this->secretKey);
    }

    /**
     * Get public key
     */
    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    /**
     * Create payment intent
     */
    public function createPaymentIntent(float $amount, string $currency, string $reference): array
    {
        $paymentIntent = PaymentIntent::create([
            'amount' => $this->convertToSmallestUnit($amount, $currency),
            'currency' => strtolower($currency),
            'metadata' => [
                'reference' => $reference,
            ],
        ]);

        return [
            'success' => true,
            'client_secret' => $paymentIntent->client_secret,
            'payment_intent_id' => $paymentIntent->id,
            'reference' => $reference,
            'status' => 'pending',
        ];
    }

    /**
     * Confirm payment
     */
    public function confirmPayment(string $paymentIntentId): array
    {
        $paymentIntent = PaymentIntent::retrieve($paymentIntentId);

        return [
            'success' => $paymentIntent->status === 'succeeded',
            'status' => $paymentIntent->status,
            'reference' => $paymentIntent->metadata['reference'] ?? null,
            'amount' => $this->convertFromSmallestUnit($paymentIntent->amount, $paymentIntent->currency),
            'currency' => $paymentIntent->currency,
        ];
    }

    /**
     * Process webhook
     */
    public function processWebhook(array $data): array
    {
        $type = $data['type'] ?? '';
        $paymentIntent = $data['data']['object'] ?? [];

        return [
            'success' => true,
            'type' => $type,
            'reference' => $paymentIntent['metadata']['reference'] ?? null,
            'status' => $paymentIntent['status'] ?? 'unknown',
            'providerReference' => $paymentIntent['id'] ?? null,
        ];
    }

    /**
     * Refund payment
     */
    public function refundPayment(string $paymentIntentId, float $amount = null): array
    {
        $params = [
            'payment_intent' => $paymentIntentId,
        ];

        if ($amount !== null) {
            $params['amount'] = $this->convertToSmallestUnit($amount, 'usd');
        }

        $refund = Refund::create($params);

        return [
            'success' => $refund->status === 'succeeded',
            'status' => $refund->status,
            'reference' => $paymentIntentId,
            'refund_id' => $refund->id,
        ];
    }

    /**
     * Convert to smallest currency unit
     */
    private function convertToSmallestUnit(float $amount, string $currency): int
    {
        $zeroDecimalCurrencies = ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'];

        if (in_array(strtolower($currency), $zeroDecimalCurrencies)) {
            return (int) $amount;
        }

        return (int) ($amount * 100);
    }

    /**
     * Convert from smallest currency unit
     */
    private function convertFromSmallestUnit(int $amount, string $currency): float
    {
        $zeroDecimalCurrencies = ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'];

        if (in_array(strtolower($currency), $zeroDecimalCurrencies)) {
            return (float) $amount;
        }

        return $amount / 100;
    }
}
