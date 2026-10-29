<?php

declare(strict_types=1);

namespace Nafiswatsiq\SubbasePayment\Tests\Checkout;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Nafiswatsiq\SubbasePayment\Data\PaymentResult;
use Nafiswatsiq\SubbasePayment\Services\PaidSubscriptionActivator;
use Nafiswatsiq\SubbasePayment\Services\VerifiedPaymentProcessor;
use Nafiswatsiq\SubbasePayment\Tests\TestCase;
use UnexpectedValueException;

final class VerifiedPaymentProcessorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('sb_test_processed_events');
        Schema::dropIfExists('sb_test_payments');

        Schema::create('sb_test_payments', static function (Blueprint $table): void {
            $table->id();
            $table->string('subscription_id')->nullable();
            $table->string('gateway_driver');
            $table->string('gateway_transaction_id')->unique();
            $table->string('payment_status');
            $table->decimal('amount', 20, 4);
            $table->char('currency', 3);
            $table->timestamp('verified_at')->nullable();
            $table->string('webhook_event_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('sb_test_processed_events', static function (Blueprint $table): void {
            $table->id();
            $table->string('gateway_driver');
            $table->string('gateway_event_id');
            $table->string('gateway_account');
            $table->unsignedBigInteger('payment_id');
            $table->string('status');
            $table->timestamps();
            $table->unique(['gateway_driver', 'gateway_account', 'gateway_event_id'], 'test_payment_event_unique');
        });

        config([
            'subbase-payment.tables.subscription_payments' => 'sb_test_payments',
            'subbase-payment.tables.processed_payment_events' => 'sb_test_processed_events',
        ]);

        DB::table('sb_test_payments')->insert([
            'gateway_driver' => 'stripe',
            'gateway_transaction_id' => 'cs_one',
            'payment_status' => 'pending',
            'amount' => '25.00',
            'currency' => 'USD',
            'metadata' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_replay_is_idempotent_for_non_financial_events(): void
    {
        $processor = new VerifiedPaymentProcessor(app(PaidSubscriptionActivator::class));
        $event = $this->event('pending', 'evt_one', '25.00');

        $this->assertSame('processed', $processor->handle('stripe', $event));
        $this->assertSame('duplicate', $processor->handle('stripe', $event));
        $this->assertSame(1, DB::table('sb_test_processed_events')->count());
    }

    public function test_late_pending_webhook_does_not_reopen_a_failed_payment(): void
    {
        $processor = new VerifiedPaymentProcessor(app(PaidSubscriptionActivator::class));

        $this->assertSame('processed', $processor->handle('stripe', $this->event('failed', 'evt_failed', '25.00')));
        $this->assertSame('failed', DB::table('sb_test_payments')->value('payment_status'));

        $this->assertSame('processed', $processor->handle('stripe', $this->event('pending', 'evt_stale', '25.00')));
        $this->assertSame('failed', DB::table('sb_test_payments')->value('payment_status'));
        $this->assertSame(2, DB::table('sb_test_processed_events')->count());
    }

    public function test_unknown_gateway_transaction_is_not_applied(): void
    {
        $processor = new VerifiedPaymentProcessor(app(PaidSubscriptionActivator::class));
        $event = new PaymentResult('paid', 'fake_other', null, [
            'event_id' => 'evt_two',
            'verified_amount' => '25.00',
            'verified_currency' => 'USD',
        ]);

        $this->assertSame('missing', $processor->handle('stripe', $event));
        $this->assertSame(0, DB::table('sb_test_processed_events')->count());
    }

    public function test_payment_is_rejected_and_transaction_rolled_back_on_amount_mismatch(): void
    {
        $processor = new VerifiedPaymentProcessor(app(PaidSubscriptionActivator::class));

        $this->expectException(UnexpectedValueException::class);

        try {
            $processor->handle('stripe', $this->event('paid', 'evt_wrong_amount', '24.99'));
        } finally {
            $this->assertSame('pending', DB::table('sb_test_payments')->value('payment_status'));
            $this->assertSame(0, DB::table('sb_test_processed_events')->count());
        }
    }

    public function test_missing_event_identifier_cannot_mark_a_payment_paid(): void
    {
        $processor = new VerifiedPaymentProcessor(app(PaidSubscriptionActivator::class));

        $this->assertSame('ignored', $processor->handle(
            'stripe',
            new PaymentResult('paid', 'cs_one', null, ['verified_amount' => '25', 'verified_currency' => 'USD']),
        ));
    }

    private function event(string $status, string $eventId, string $amount): PaymentResult
    {
        return new PaymentResult($status, 'cs_one', null, [
            'event_id' => $eventId,
            'verified_amount' => $amount,
            'verified_currency' => 'USD',
        ]);
    }
}
