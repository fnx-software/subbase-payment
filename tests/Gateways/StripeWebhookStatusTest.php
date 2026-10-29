<?php

declare(strict_types=1);

namespace Nafiswatsiq\SubbasePayment\Tests\Gateways;

use Illuminate\Support\Facades\Config;
use Nafiswatsiq\SubbasePayment\Data\PaymentResult;
use Nafiswatsiq\SubbasePayment\Gateways\StripeGateway;
use Nafiswatsiq\SubbasePayment\Tests\TestCase;

final class StripeWebhookStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('subbase-payment.gateways.stripe.webhook_secret', 'whsec_test_123');
    }

    public function test_unpaid_completed_checkout_is_not_settled_as_paid(): void
    {
        $result = $this->signedEvent('checkout.session.completed', 'unpaid');

        $this->assertSame('pending', $result->status);
        $this->assertSame('cs_test', $result->transactionId);
    }

    public function test_paid_completed_checkout_is_settled(): void
    {
        $this->assertSame('paid', $this->signedEvent('checkout.session.completed', 'paid')->status);
    }

    public function test_async_success_can_complete_a_payment(): void
    {
        $this->assertSame('paid', $this->signedEvent('checkout.session.async_payment_succeeded', 'paid')->status);
    }

    private function signedEvent(string $eventType, string $paymentStatus): PaymentResult
    {
        $payload = [
            'id' => 'evt_123',
            'type' => $eventType,
            'data' => [
                'object' => [
                    'id' => 'cs_test',
                    'object' => 'checkout.session',
                    'payment_status' => $paymentStatus,
                ],
            ],
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test_123');

        return (new StripeGateway)->handleWebhook(
            [...$payload, '_raw_body' => $body],
            ['stripe-signature' => "t={$timestamp},v1={$signature}"],
        );
    }
}
