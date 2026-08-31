<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymobGateway
{
    protected string $apiKey;

    protected string $integrationId;

    protected string $iframeId;

    protected string $hmacSecret;

    public function __construct()
    {
        $this->apiKey = (string) config('services.paymob.api_key');
        $this->integrationId = (string) config('services.paymob.integration_id');
        $this->iframeId = (string) config('services.paymob.iframe_id');
        $this->hmacSecret = (string) config('services.paymob.hmac_secret');
    }

    /**
     * @param  array<string, mixed>  $billingData
     * @return array<string, mixed>
     */
    public function initiatePayment(
        int $amountCents,
        string $merchantOrderId,
        array $billingData,
        string $currency = 'EGP',
    ): array {
        $authToken = $this->authenticate();

        $orderResponse = Http::post('https://accept.paymob.com/api/ecommerce/orders', [
            'auth_token' => $authToken,
            'delivery_needed' => false,
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'merchant_order_id' => $merchantOrderId,
            'items' => [],
        ]);

        if (! $orderResponse->successful()) {
            Log::error('Paymob order creation failed', ['response' => $orderResponse->json()]);
            throw new \RuntimeException('Failed to create Paymob order.');
        }

        $orderId = $orderResponse->json('id');

        $paymentKeyResponse = Http::post('https://accept.paymob.com/api/acceptance/payment_keys', [
            'auth_token' => $authToken,
            'amount_cents' => $amountCents,
            'expiration' => 3600,
            'order_id' => $orderId,
            'billing_data' => array_merge([
                'apartment' => 'NA',
                'email' => $billingData['email'] ?? 'customer@example.com',
                'floor' => 'NA',
                'first_name' => $billingData['first_name'] ?? 'Customer',
                'street' => 'NA',
                'building' => 'NA',
                'phone_number' => $billingData['phone'] ?? '0000000000',
                'shipping_method' => 'NA',
                'postal_code' => 'NA',
                'city' => 'NA',
                'country' => 'EG',
                'last_name' => $billingData['last_name'] ?? 'User',
                'state' => 'NA',
            ], $billingData),
            'currency' => $currency,
            'integration_id' => (int) $this->integrationId,
        ]);

        if (! $paymentKeyResponse->successful()) {
            Log::error('Paymob payment key failed', ['response' => $paymentKeyResponse->json()]);
            throw new \RuntimeException('Failed to create Paymob payment key.');
        }

        $paymentToken = $paymentKeyResponse->json('token');

        return [
            'order_id' => $orderId,
            'payment_token' => $paymentToken,
            'iframe_url' => "https://accept.paymob.com/api/acceptance/iframes/{$this->iframeId}?payment_token={$paymentToken}",
            'provider' => 'paymob',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyWebhookSignature(array $payload, string $receivedHmac): bool
    {
        if ($this->hmacSecret === '') {
            return false;
        }

        $fields = [
            $payload['amount_cents'] ?? '',
            $payload['created_at'] ?? '',
            $payload['currency'] ?? '',
            $payload['error_occured'] ?? '',
            $payload['has_parent_transaction'] ?? '',
            $payload['id'] ?? '',
            $payload['integration_id'] ?? '',
            $payload['is_3d_secure'] ?? '',
            $payload['is_auth'] ?? '',
            $payload['is_capture'] ?? '',
            $payload['is_refunded'] ?? '',
            $payload['is_standalone_payment'] ?? '',
            $payload['is_voided'] ?? '',
            $payload['order'] ?? '',
            $payload['owner'] ?? '',
            $payload['pending'] ?? '',
            $payload['source_data_pan'] ?? '',
            $payload['source_data_sub_type'] ?? '',
            $payload['source_data_type'] ?? '',
            $payload['success'] ?? '',
        ];

        $concatenated = implode('', array_map(fn ($v) => is_bool($v) ? ($v ? 'true' : 'false') : (string) $v, $fields));
        $calculated = hash_hmac('sha512', $concatenated, $this->hmacSecret);

        return hash_equals($calculated, $receivedHmac);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function parseWebhook(array $payload): array
    {
        $obj = $payload['obj'] ?? $payload;

        return [
            'transaction_id' => (string) ($obj['id'] ?? ''),
            'order_id' => (string) ($obj['order']['id'] ?? $obj['order'] ?? ''),
            'merchant_order_id' => (string) ($obj['order']['merchant_order_id'] ?? ''),
            'amount_cents' => (int) ($obj['amount_cents'] ?? 0),
            'success' => filter_var($obj['success'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'pending' => filter_var($obj['pending'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'is_refunded' => filter_var($obj['is_refunded'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'currency' => (string) ($obj['currency'] ?? 'EGP'),
        ];
    }

    protected function authenticate(): string
    {
        $response = Http::post('https://accept.paymob.com/api/auth/tokens', [
            'api_key' => $this->apiKey,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Paymob authentication failed.');
        }

        return (string) $response->json('token');
    }
}
