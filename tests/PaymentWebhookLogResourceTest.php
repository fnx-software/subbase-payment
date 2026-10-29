<?php

namespace Nafiswatsiq\SubbasePayment\Tests;

use Nafiswatsiq\SubbasePayment\Models\PaymentWebhookLog;
use Nafiswatsiq\SubbasePayment\Filament\Resources\PaymentWebhookLogs\PaymentWebhookLogResource;
use Nafiswatsiq\SubbasePayment\Support\SubbasePaymentPermission;

class PaymentWebhookLogResourceTest extends TestCase
{
    public function test_resource_can_be_instantiated()
    {
        $this->assertEquals(PaymentWebhookLog::class, PaymentWebhookLogResource::getModel());
        $this->assertFalse(PaymentWebhookLogResource::canCreate());
        $this->assertFalse(PaymentWebhookLogResource::canEdit(new PaymentWebhookLog()));
    }

    public function test_permission_fallback_denies_access_without_a_gate()
    {
        $this->assertFalse(SubbasePaymentPermission::allows(null, 'viewAny', PaymentWebhookLog::class));
    }
}
