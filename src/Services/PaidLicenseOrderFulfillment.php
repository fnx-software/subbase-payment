<?php

declare(strict_types=1);

namespace Nafiswatsiq\SubbasePayment\Services;

use Nafiswatsiq\Subbase\Models\LicenseOrder;
use Nafiswatsiq\Subbase\Services\LicenseFulfillmentService;
use Nafiswatsiq\SubbasePayment\Support\GatewayMoney;
use UnexpectedValueException;

final class PaidLicenseOrderFulfillment
{
    public function __construct(private readonly LicenseFulfillmentService $licenses) {}

    /**
     * Invoked only from a verified gateway event inside the locked payment
     * transaction. Never expose this operation on an unverified user endpoint.
     */
    public function fulfill(object $payment, array $metadata): LicenseOrder
    {
        $orderId = $metadata['license_order_id'] ?? null;
        if (! is_numeric($orderId) || (int) $orderId < 1) {
            throw new UnexpectedValueException('Verified license payment does not reference a valid order.');
        }

        $order = LicenseOrder::query()->lockForUpdate()->findOrFail((int) $orderId);

        if (strtoupper((string) $order->currency) !== strtoupper((string) $payment->currency)
            || ! GatewayMoney::same((string) $order->total_amount, (string) $payment->amount, (string) $order->currency)
            || (string) $order->purchaser_type !== (string) $payment->billable_type
            || (string) $order->purchaser_id !== (string) $payment->billable_id
            || (string) $order->offer_id !== (string) ($metadata['license_offer_id'] ?? '')
            || (string) ($order->provider_id ?? '') !== (string) ($metadata['license_provider_id'] ?? '')
        ) {
            throw new UnexpectedValueException('License checkout amount, purchaser or catalog snapshot mismatch.');
        }

        return $this->licenses->fulfillPaidOrder($order, (int) $payment->id);
    }
}
