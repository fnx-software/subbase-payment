<?php

declare(strict_types=1);

namespace Nafiswatsiq\SubbasePayment\Services;

use Illuminate\Support\Facades\DB;
use Nafiswatsiq\Subbase\Models\LicenseOrder;
use Nafiswatsiq\SubbasePayment\Data\PaymentRequest;
use Nafiswatsiq\SubbasePayment\Data\PaymentResult;
use Nafiswatsiq\SubbasePayment\PaymentManager;
use RuntimeException;

final class LicenseCheckoutService
{
    /**
     * Start a payment from the persisted, server-priced order snapshot.
     * A started checkout cannot be charged a second time by retrying its ID.
     * If the provider times out, administrators must reconcile the session.
     */
    public function begin(
        LicenseOrder $order,
        PaymentManager $gateway,
        string $customerName,
        string $customerEmail,
        string $actorId,
    ): PaymentResult {
        $driver = (string) config('subbase-payment.driver');
        if ($driver === '') {
            throw new RuntimeException('No payment gateway is configured.');
        }

        $order = DB::transaction(static function () use ($order): LicenseOrder {
            $record = LicenseOrder::query()->lockForUpdate()->findOrFail($order->getKey());

            if ($record->status !== 'pending') {
                throw new RuntimeException('Checkout was already started. Do not pay for an order twice.');
            }

            $record->update(['status' => 'checkout_started']);

            return $record;
        });

        $plan = $order->offer()->firstOrFail()->plan()->firstOrFail();

        $result = $gateway->driver($driver)->charge(new PaymentRequest(
            $plan,
            (string) $order->total_amount,
            (string) $order->currency,
            $customerName,
            $customerEmail,
            route('subbase-payment.orders.show', ['order' => $order->public_id]),
            route('subbase-payment.orders.show', ['order' => $order->public_id]),
        ));

        if (! is_string($result->transactionId) || $result->transactionId === ''
            || ! is_string($result->approvalUrl) || ! str_starts_with($result->approvalUrl, 'https://')
        ) {
            throw new RuntimeException('Gateway checkout response is incomplete; manual reconciliation is required.');
        }

        DB::transaction(static function () use ($order, $driver, $result, $customerName, $customerEmail, $actorId): void {
            $record = LicenseOrder::query()->lockForUpdate()->findOrFail($order->getKey());
            if ($record->status !== 'checkout_started') {
                throw new RuntimeException('License checkout is no longer available.');
            }

            DB::table(config('subbase-payment.tables.subscription_payments', 'subscription_payments'))->insert([
                'gateway_driver' => $driver,
                'gateway_transaction_id' => $result->transactionId,
                'payment_status' => 'pending',
                'billable_type' => $record->purchaser_type,
                'billable_id' => $record->purchaser_id,
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'amount' => $record->total_amount,
                'currency' => $record->currency,
                'metadata' => json_encode([
                    'purchase_type' => 'license_order',
                    'license_order_id' => $record->getKey(),
                    'license_offer_id' => $record->offer_id,
                    'license_provider_id' => $record->provider_id,
                    'user_id' => $actorId,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $record->update(['status' => 'awaiting_payment']);
        });

        return $result;
    }
}
