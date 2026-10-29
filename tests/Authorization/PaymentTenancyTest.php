<?php

declare(strict_types=1);

namespace Nafiswatsiq\SubbasePayment\Tests\Authorization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Nafiswatsiq\SubbasePayment\Support\PaymentTenancy;
use Nafiswatsiq\SubbasePayment\Tests\TestCase;

final class PaymentTenantTestModel extends Model
{
    protected $table = 'payment_tenant_demo';
}

final class PaymentTenancyTest extends TestCase
{
    public function test_enabled_tenant_mode_without_access_resolver_is_closed(): void
    {
        config(['subbase-payment.tenancy.enabled' => true, 'subbase-payment.tenancy.access' => null]);

        $this->assertFalse(PaymentTenancy::allows(new PaymentTenantTestModel, 'view', new User));
        $this->assertStringContainsString('1 = 0', PaymentTenancy::scope(PaymentTenantTestModel::query())->toSql());
    }
}
