<?php

declare(strict_types=1);

namespace Nafiswatsiq\SubbasePayment\Services;

use Illuminate\Support\Facades\DB;
use Nafiswatsiq\SubbasePayment\Data\PaymentResult;
use Nafiswatsiq\SubbasePayment\Events\LicenseOrderFulfilled;
use Nafiswatsiq\SubbasePayment\Events\PaymentReceived;
use Nafiswatsiq\SubbasePayment\Support\GatewayMoney;
use UnexpectedValueException;

final class VerifiedPaymentProcessor
{
    public function __construct(private readonly PaidSubscriptionActivator $activator) {}

    /**
     * Handle a gateway-verified (signature checked) webhook.
     *
     * @return string One of processed, duplicate, ignored or missing.
     */
    public function handle(string $driver, PaymentResult $result): string
    {
        $eventId = $result->data['event_id'] ?? null;

        if (! is_string($eventId) || $eventId === '' || strlen($eventId) > 191
            || ! is_string($result->transactionId) || $result->transactionId === ''
        ) {
            return 'ignored';
        }

        if (! in_array($result->status, ['paid', 'pending', 'approved', 'failed', 'canceled'], true)) {
            return 'ignored';
        }

        return DB::transaction(function () use ($driver, $result, $eventId): string {
            $paymentTable = config('subbase-payment.tables.subscription_payments', 'subscription_payments');
            $eventsTable = config('subbase-payment.tables.processed_payment_events', 'subbase_processed_payment_events');
            $payment = DB::table($paymentTable)
                ->where('gateway_driver', $driver)
                ->where('gateway_transaction_id', $result->transactionId)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                return 'missing';
            }

            $account = 'default'; // One configured merchant account per installed driver.
            $created = DB::table($eventsTable)->insertOrIgnore([
                'gateway_driver' => $driver,
                'gateway_event_id' => $eventId,
                'gateway_account' => $account,
                'payment_id' => $payment->id,
                'status' => $result->status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($created !== 1) {
                return 'duplicate';
            }

            $alreadyPaid = in_array($payment->payment_status, ['paid'], true);

            if ($result->status === 'paid' && ! $alreadyPaid) {
                $amount = $result->data['verified_amount'] ?? null;
                $currency = $result->data['verified_currency'] ?? null;

                if (! is_string($amount) || ! is_string($currency)
                    || strtoupper($currency) !== strtoupper((string) $payment->currency)
                    || ! GatewayMoney::same((string) $payment->amount, $amount, $currency)
                ) {
                    throw new UnexpectedValueException('Gateway amount or currency does not match the payment intent.');
                }

                $metadata = is_string($payment->metadata ?? null)
                    ? (json_decode($payment->metadata, true) ?: []) : [];

                $purchaseType = $metadata['purchase_type'] ?? 'subscription';
                if ($purchaseType === 'license_order') {
                    // The order is fulfilled in this SAME DB transaction. A
                    // legitimate paid gateway event never creates a subscription
                    // for the reseller; only the eventual key recipient redeems.
                    $licenseOrder = app(PaidLicenseOrderFulfillment::class)->fulfill($payment, $metadata);
                    $subscriptionId = null;
                } elseif ($purchaseType === 'subscription') {
                    $licenseOrder = null;
                    $subscriptionId = $this->activator->activate($payment);
                } else {
                    throw new UnexpectedValueException('Unsupported checkout purpose.');
                }

                DB::table($paymentTable)->where('id', $payment->id)->update([
                    'payment_status' => 'paid',
                    'subscription_id' => $subscriptionId,
                    'verified_at' => now(),
                    'webhook_event_id' => $eventId,
                    'updated_at' => now(),
                ]);

                if ($licenseOrder !== null) {
                    DB::afterCommit(static function () use ($licenseOrder, $driver, $result): void {
                        event(new LicenseOrderFulfilled(
                            (string) $licenseOrder->public_id,
                            $driver,
                            (string) $result->transactionId,
                        ));
                    });
                } else {
                    $updatedRecord = DB::table($paymentTable)->where('id', $payment->id)->first();

                    DB::afterCommit(static function () use ($updatedRecord, $metadata): void {
                        event(new PaymentReceived($updatedRecord, $metadata));
                    });
                }

                return 'processed';
            }

            // Terminal provider failures must not be reopened by late pending
            // or approved notifications. A subsequent *verified paid* event is
            // still permitted to settle a genuinely delayed capture.
            $terminal = in_array($payment->payment_status, ['paid', 'failed', 'canceled'], true);
            $stale = $terminal && in_array($result->status, ['pending', 'approved'], true);

            if (! $alreadyPaid && ! $stale && $result->status !== 'paid') {
                DB::table($paymentTable)->where('id', $payment->id)->update([
                    'payment_status' => $result->status,
                    'webhook_event_id' => $eventId,
                    'updated_at' => now(),
                ]);
            }

            return 'processed';
        }, 3);
    }
}
