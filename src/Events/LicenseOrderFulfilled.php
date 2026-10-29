<?php

declare(strict_types=1);

namespace Nafiswatsiq\SubbasePayment\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class LicenseOrderFulfilled
{
    use Dispatchable;

    public function __construct(
        public readonly string $licenseOrderId,
        public readonly string $gatewayDriver,
        public readonly string $gatewayTransactionId,
    ) {}
}
