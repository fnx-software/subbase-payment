<?php

declare(strict_types=1);

namespace Nafiswatsiq\SubbasePayment\Tests\Authorization;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Nafiswatsiq\SubbasePayment\Models\SubscriptionPayment;
use Nafiswatsiq\SubbasePayment\Support\SubbasePaymentPermission;
use Nafiswatsiq\SubbasePayment\Tests\TestCase;

final class SubbasePaymentPermissionTest extends TestCase
{
    public function test_an_authenticated_user_without_explicit_grant_is_denied(): void
    {
        $this->assertFalse(
            SubbasePaymentPermission::allows(null, 'viewAny', SubscriptionPayment::class, new User()),
        );
    }

    public function test_a_native_laravel_gate_is_supported_without_spatie(): void
    {
        Gate::define('ViewAny:SubscriptionPayment', fn (User $actor): bool => $actor->name === 'billing admin');

        $admin = new User();
        $admin->name = 'billing admin';

        $other = new User();
        $other->name = 'other';

        $this->assertTrue(
            SubbasePaymentPermission::allows(
                null,
                'viewAny',
                SubscriptionPayment::class,
                $admin,
            ),
        );
        $this->assertFalse(
            SubbasePaymentPermission::allows(
                null,
                'viewAny',
                SubscriptionPayment::class,
                $other,
            ),
        );
    }

    public function test_no_ability_cannot_be_implicitly_allowed(): void
    {
        $this->assertFalse(SubbasePaymentPermission::allows(null, actor: new User()));
    }
}
