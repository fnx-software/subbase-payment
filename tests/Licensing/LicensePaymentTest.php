<?php

declare(strict_types=1);

namespace Nafiswatsiq\SubbasePayment\Tests\Licensing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Laravelcm\Subscriptions\Traits\HasPlanSubscriptions;
use Nafiswatsiq\Subbase\Models\LicenseKey;
use Nafiswatsiq\Subbase\Models\LicenseOffer;
use Nafiswatsiq\Subbase\Models\LicenseOrder;
use Nafiswatsiq\Subbase\Models\LicenseProduct;
use Nafiswatsiq\Subbase\Models\LicenseProvider;
use Nafiswatsiq\Subbase\Models\Plan;
use Nafiswatsiq\Subbase\Models\Subscription;
use Nafiswatsiq\Subbase\Services\LicenseInventoryService;
use Nafiswatsiq\Subbase\Services\LicenseOrderService;
use Nafiswatsiq\SubbasePayment\Contracts\PaymentGatewayInterface;
use Nafiswatsiq\SubbasePayment\Data\PaymentRequest;
use Nafiswatsiq\SubbasePayment\Data\PaymentResult;
use Nafiswatsiq\SubbasePayment\PaymentManager;
use Nafiswatsiq\SubbasePayment\Services\LicenseCheckoutService;
use Nafiswatsiq\SubbasePayment\Services\VerifiedPaymentProcessor;
use Nafiswatsiq\SubbasePayment\Tests\TestCase;
use UnexpectedValueException;

final class LicenseGatewayTestDouble implements PaymentGatewayInterface
{
    public function charge(PaymentRequest $request): PaymentResult
    {
        return new PaymentResult('pending', 'cs_bulk_12', 'https://sandbox.example/checkout/abc');
    }

    public function cancel(string $transactionId): PaymentResult
    {
        return new PaymentResult('canceled', $transactionId);
    }

    public function handleWebhook(array $payload, array $headers = []): PaymentResult
    {
        return new PaymentResult('paid', 'cs_bulk_12', null, [
            'event_id' => 'evt_paid',
            'verified_amount' => '1200.00',
            'verified_currency' => 'USD',
        ]);
    }

    public function name(): string
    {
        return 'Test Gateway';
    }

    public function logo(): ?string
    {
        return null;
    }
}

final class LicensedResellerOrganization extends Model
{
    use HasPlanSubscriptions;

    protected $table = 'sb_pay_lic_organizations';

    protected $guarded = [];
}

final class LicensePaymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('x', 32)),
            'subbase-payment.driver' => 'test-license',
            'laravel-subscriptions.tables.plans' => 'sb_pay_lic_plans',
            'laravel-subscriptions.tables.subscriptions' => 'sb_pay_lic_subscriptions',
            'laravel-subscriptions.models.plan' => Plan::class,
            'laravel-subscriptions.models.subscription' => Subscription::class,
            'subbase.models.plan' => Plan::class,
            'subbase.licensing.key_prefix' => 'LIC',
        ]);

        Schema::create('sb_pay_lic_organizations', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('sb_pay_lic_plans', static function (Blueprint $table): void {
            $table->id();
            $table->string('slug');
            $table->text('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('price', 20, 4)->default('150');
            $table->char('currency', 3)->default('USD');
            $table->unsignedInteger('invoice_period')->default(1);
            $table->string('invoice_interval')->default('year');
            $table->unsignedInteger('trial_period')->default(0);
            $table->string('trial_interval')->default('day');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('sb_pay_lic_subscriptions', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('plan_id');
            $table->string('subscriber_type');
            $table->unsignedBigInteger('subscriber_id');
            $table->string('slug');
            $table->text('name');
            $table->text('description')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $root = dirname((new \ReflectionClass(LicenseOrder::class))->getFileName(), 2);
        foreach (['create_license_catalog_and_providers_table', 'create_license_orders_and_keys_table'] as $migration) {
            (require $root.'/database/migrations/'.$migration.'.php')->up();
        }

        foreach ([
            'create_subscription_payments_table',
            'create_payment_webhook_logs_table',
            'add_billable_to_subscription_payments_table',
            'create_processed_payment_events_table',
            'upgrade_subscription_payment_decimal_columns',
        ] as $migration) {
            (require dirname(__DIR__, 2).'/database/migrations/'.$migration.'.php')->up();
        }

        DB::table('sb_pay_lic_organizations')->insert([
            ['id' => 1, 'name' => 'Reseller', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Customer', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('sb_pay_lic_plans')->insert([
            'id' => 17,
            'slug' => 'crm-individual',
            'name' => json_encode(['en' => 'CRM Individual'], JSON_THROW_ON_ERROR),
            'description' => json_encode(['en' => 'Annual'], JSON_THROW_ON_ERROR),
            'price' => '150.00',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        LicenseProduct::query()->create(['sku' => 'crm', 'name' => 'Fenix CRM', 'is_active' => true]);
        LicenseOffer::query()->create([
            'product_id' => LicenseProduct::query()->value('id'),
            'plan_id' => 17,
            'sku' => 'crm-solo-year',
            'currency' => 'USD',
            'retail_price' => '150.00',
            'wholesale_price' => '100.00',
            'wholesale_minimum' => 12,
            'term_count' => 1,
            'term_unit' => 'year',
            'is_active' => true,
        ]);
        LicenseProvider::query()->create([
            'owner_type' => LicensedResellerOrganization::class,
            'owner_id' => '1',
            'business_name' => 'Reseller shop',
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        Gate::define('purchaseWholesaleLicenses', static fn (User $actor, LicenseProvider $provider): bool => $actor->name === 'merchant' && $provider->approved());
        Gate::define('viewPurchasedLicenseKeys', static fn (User $actor, LicenseOrder $order): bool => $actor->name === 'merchant' && $order->channel === 'wholesale');

        app(PaymentManager::class)->register('test-license', new LicenseGatewayTestDouble);
    }

    public function test_signed_paid_event_issues_twelve_unique_licenses_without_subscribing_reseller(): void
    {
        $order = $this->startCheckout();
        $this->assertSame('awaiting_payment', $order->refresh()->status);
        $this->assertSame(0, LicenseKey::query()->count());

        $processor = app(VerifiedPaymentProcessor::class);

        $this->assertSame('processed', $processor->handle('test-license', $this->paidEvent('evt_1')));
        $this->assertSame('duplicate', $processor->handle('test-license', $this->paidEvent('evt_1')));
        $this->assertSame('processed', $processor->handle('test-license', $this->paidEvent('evt_2')));

        $this->assertSame('fulfilled', $order->refresh()->status);
        $this->assertSame(12, LicenseKey::query()->count());
        $this->assertSame(12, LicenseKey::query()->distinct()->count('code_hash'));
        $this->assertSame(0, DB::table('sb_pay_lic_subscriptions')->count());
        $this->assertSame('paid', DB::table('subscription_payments')->value('payment_status'));
        $this->assertNull(DB::table('subscription_payments')->value('subscription_id'));
        $this->assertSame(12, count(app(LicenseInventoryService::class)->reveal($order, $this->actor())));
    }

    public function test_wrong_amount_rolls_back_keys_and_payment_state(): void
    {
        $order = $this->startCheckout();
        $this->expectException(UnexpectedValueException::class);
        try {
            app(VerifiedPaymentProcessor::class)->handle('test-license', $this->paidEvent('evt_wrong', '1199.99'));
        } finally {
            $this->assertSame('awaiting_payment', $order->refresh()->status);
            $this->assertSame(0, LicenseKey::query()->count());
            $this->assertSame('pending', DB::table('subscription_payments')->value('payment_status'));
            $this->assertSame(0, DB::table('subbase_processed_payment_events')->count());
        }
    }

    public function test_checkout_metadata_cannot_fulfill_another_resellers_order(): void
    {
        $order = $this->startCheckout();
        $payment = DB::table('subscription_payments')->first();
        $data = json_decode($payment->metadata, true, 512, JSON_THROW_ON_ERROR);
        $data['license_provider_id'] = 999;
        DB::table('subscription_payments')->where('id', $payment->id)->update([
            'metadata' => json_encode($data, JSON_THROW_ON_ERROR),
        ]);

        $this->expectException(UnexpectedValueException::class);

        try {
            app(VerifiedPaymentProcessor::class)->handle('test-license', $this->paidEvent('evt_forged'));
        } finally {
            $this->assertSame(0, LicenseKey::query()->count());
            $this->assertSame('awaiting_payment', $order->refresh()->status);
        }
    }

    private function startCheckout(): LicenseOrder
    {
        $offer = LicenseOffer::query()->firstOrFail();
        $provider = LicenseProvider::query()->firstOrFail();
        $owner = LicensedResellerOrganization::query()->findOrFail(1);

        $order = app(LicenseOrderService::class)->place(
            $offer, $owner, $this->actor(), 12, $provider, 'license_pay_order_0001',
        );
        $result = app(LicenseCheckoutService::class)->begin(
            $order, app(PaymentManager::class), 'Reseller Shop', 'orders@example.test', 'merchant_1',
        );
        $this->assertSame('https://sandbox.example/checkout/abc', $result->approvalUrl);
        $this->assertSame('1200.00', (string) DB::table('subscription_payments')->value('amount'));

        return $order;
    }

    private function actor(): User
    {
        $actor = new User;
        $actor->name = 'merchant';

        return $actor;
    }

    private function paidEvent(string $eventId, string $amount = '1200.00'): PaymentResult
    {
        return new PaymentResult('paid', 'cs_bulk_12', null, [
            'event_id' => $eventId,
            'verified_amount' => $amount,
            'verified_currency' => 'USD',
        ]);
    }
}
