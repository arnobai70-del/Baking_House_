<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * TEST-MODE stand-in for bKash, Nagad and card gateways. It is NOT connected to any real payment
 * network. The customer is sent to a signed, simulated hosted payment page inside this app, and
 * the outcome comes back as an HMAC-SHA256-signed callback that goes through exactly the same
 * server-side verification and idempotency path a real provider callback will use.
 *
 * Signature: hex(HMAC_SHA256(secret, timestamp + "." + rawBody)) in X-BH-Test-Signature, with
 * X-BH-Test-Timestamp (Unix seconds). Callbacks older than the tolerance are rejected.
 */
class TestPaymentGateway implements PaymentGateway
{
    public const SIGNATURE_HEADER = 'X-BH-Test-Signature';

    public const TIMESTAMP_HEADER = 'X-BH-Test-Timestamp';

    public function __construct(private PaymentMethod $method, private string $secret, private int $toleranceSeconds) {}

    public function method(): PaymentMethod
    {
        return $this->method;
    }

    public function mode(): string
    {
        return 'test';
    }

    public function checkoutUrl(Payment $payment): string
    {
        return URL::temporarySignedRoute('payments.test.show', now()->addMinutes(30), ['reference' => $payment->reference]);
    }

    /**
     * Build a signed callback (used by the simulator and by tests).
     *
     * @param  array<string, mixed>  $payload
     * @return array{body: string, headers: array<string, string>}
     */
    public function signedCallback(array $payload, ?int $timestamp = null): array
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp ??= now()->getTimestamp();

        return [
            'body' => $body,
            'headers' => [
                self::SIGNATURE_HEADER => hash_hmac('sha256', $timestamp.'.'.$body, $this->secret),
                self::TIMESTAMP_HEADER => (string) $timestamp,
            ],
        ];
    }

    public function verifyCallback(string $rawBody, array $headers): array
    {
        $signature = (string) ($headers[self::SIGNATURE_HEADER] ?? '');
        $timestamp = (string) ($headers[self::TIMESTAMP_HEADER] ?? '');

        if ($signature === '' || ! ctype_digit($timestamp)) {
            throw new InvalidCallbackException('Missing signature.');
        }
        if (abs(now()->getTimestamp() - (int) $timestamp) > $this->toleranceSeconds) {
            throw new InvalidCallbackException('Stale callback.');
        }
        if (! hash_equals(hash_hmac('sha256', $timestamp.'.'.$rawBody, $this->secret), $signature)) {
            throw new InvalidCallbackException('Bad signature.');
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload) || ! isset($payload['event_id'], $payload['type'], $payload['reference'])) {
            throw new InvalidCallbackException('Malformed payload.');
        }

        return $payload;
    }

    public function refund(Payment $payment, int $amountPaisa): string
    {
        return 'TEST-RF-'.Str::upper(Str::random(12));
    }
}
