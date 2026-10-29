<?php

declare(strict_types=1);

namespace Nafiswatsiq\SubbasePayment\Tests\Checkout;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravelcm\Subscriptions\Traits\HasPlanSubscriptions;
use Nafiswatsiq\Subbase\Models\Plan;
use Nafiswatsiq\Subbase\Models\Subscription;
use Nafiswatsiq\SubbasePayment\Data\PaymentResult;
use Nafiswatsiq\SubbasePayment\Services\PaidSubscriptionActivator;
use Nafiswatsiq\SubbasePayment\Services\VerifiedPaymentProcessor;
use Nafiswatsiq\SubbasePayment\Tests\TestCase;

final class BillableExampleOrganization extends Model
{
    use HasPlanSubscriptions;

    protected $table = 'sb_test_organizations';

    protected $guarded = [];
}

final class PaidOrganizationSettlementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('sb_test_organizations', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('sb_test_plans', static function (Blueprint $table): void {
            $table->id();
            $table->string('slug');
            $table->text('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('price', 20, 4);
            $table->string('currency', 3);
            $table->unsignedInteger('trial_period')->default(0);
            $table->string('trial_interval')->default('day');
            $table->unsignedInteger('invoice_period')->default(1);
            $table->string('invoice_interval')->default('month');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('sb_test_subscriptions', static function (Blueprint $table): void {
            $table->id();
            $table->string('subscriber_type');
            $table->unsignedBigInteger('subscriber_id');
            $table->unsignedBigInteger('plan_id');
            $table->string('slug');
            $table->text('name');
            $table->text('description')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('sb_test_settlement_payments', static function (Blueprint $table): void {
            $table->id();
            $table->string('subscription_id')->nullable();
            $table->string('billable_type')->nullable();
            $table->string('billable_id')->nullable();
            $table->string('gateway_driver');
            $table->string('gateway_transaction_id')->unique();
            $table->string('payment_status');
            $table->decimal('amount', 20, 4);
            $table->string('currency', 3);
            $table->timestamp('verified_at')->nullable();
            $table->string('webhook_event_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('sb_test_settlement_events', static function (Blueprint $table): void {
            $table->id();
            $table->string('gateway_driver');
            $table->string('gateway_event_id');
            $table->string('gateway_account');
            $table->unsignedBigInteger('payment_id');
            $table->string('status');
            $table->timestamps();
            $table->unique(['gateway_driver', 'gateway_account', 'gateway_event_id']);
        });

        config([
            'subbase.models.plan' => Plan::class,
            'subbase-payment.tables.subscription_payments' => 'sb_test_settlement_payments',
            'subbase-payment.tables.processed_payment_events' => 'sb_test_settlement_events',
            'subbase-payment.checkout.billable_models' => [
                'organization' => BillableExampleOrganization::class,
            ],
            'subbase-payment.checkout.subscription_name' => 'default',
            'laravel-subscriptions.tables.plans' => 'sb_test_plans',
            'laravel-subscriptions.tables.subscriptions' => 'sb_test_subscriptions',
            'laravel-subscriptions.models.plan' => Plan::class,
            'laravel-subscriptions.models.subscription' => Subscription::class,
        ]);

        DB::table('sb_test_organizations')->insert([
            ['id' => 1, 'name' => 'Primary business', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Second business', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('sb_test_plans')->insert([
            'id' => 8,
            'slug' => 'pro',
            'name' => json_encode(['en' => 'Pro'], JSON_THROW_ON_ERROR),
            'description' => json_encode(['en' => 'A plan'], JSON_THROW_ON_ERROR),
            'is_active' => true,
            'price' => '25.00',
            'currency' => 'USD',
            'trial_period' => 0,
            'trial_interval' => 'day',
            'invoice_period' => 1,
            'invoice_interval' => 'month',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sb_test_settlement_payments')->insert([
            'gateway_driver' => 'stripe',
            'gateway_transaction_id' => 'cs_organization',
            'billable_type' => BillableExampleOrganization::class,
            'billable_id' => '1',
            'payment_status' => 'pending',
            'amount' => '25.00',
            'currency' => 'USD',
            'metadata' => json_encode([
                'plan_id' => 8,
                'billable_type' => BillableExampleOrganization::class,
                'billable_id' => '1',
                'subscription_action' => 'new',
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_confirmed_payment_activates_the_correct_organization_exactly_once(): void
    {
        $processor = new VerifiedPaymentProcessor(app(PaidSubscriptionActivator::class));

        $this->assertSame('processed', $processor->handle('stripe', $this->verified('evt_1')));
        $this->assertSame('duplicate', $processor->handle('stripe', $this->verified('evt_1')));
        $this->assertSame('processed', $processor->handle('stripe', $this->verified('evt_2')));
        $this->assertSame(1, DB::table('sb_test_subscriptions')->count());
        $this->assertSame('1', (string) DB::table('sb_test_subscriptions')->value('subscriber_id'));
        $this->assertSame('paid', DB::table('sb_test_settlement_payments')->value('payment_status'));
        $this->assertNotNull(DB::table('sb_test_settlement_payments')->value('subscription_id'));
        $this->assertSame(2, DB::table('sb_test_settlement_events')->count());
    }

    public function test_unallowlisted_model_is_rejected_without_marking_paid(): void
    {
        config(['subbase-payment.checkout.billable_models' => ['user' => \Illuminate\Foundation\Auth\User::class]]);

        $this->expectException(\RuntimeException::class);

        try {
            (new VerifiedPaymentProcessor(app(PaidSubscriptionActivator::class)))
                ->handle('stripe', $this->verified('evt_bad_owner'));
        } finally {
            $this->assertSame('pending', DB::table('sb_test_settlement_payments')->value('payment_status'));
            $this->assertSame(0, DB::table('sb_test_settlement_events')->count());
        }
    }

    private function verified(string $id): PaymentResult
    {
        return new PaymentResult('paid', 'cs_organization', null, [
            'event_id' => $id,
            'verified_amount' => '25.00',
            'verified_currency' => 'USD',
        ]);
    }
}
