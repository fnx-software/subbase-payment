<?php

declare(strict_types=1);

namespace Nafiswatsiq\SubbasePayment\Tests\Checkout;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Laravelcm\Subscriptions\Traits\HasPlanSubscriptions;
use Nafiswatsiq\SubbasePayment\Support\AuthenticatedBillableResolver;
use Nafiswatsiq\SubbasePayment\Tests\TestCase;

class SubscribableTestUser extends User
{
    use HasPlanSubscriptions;
}

final class AuthenticatedBillableResolverTest extends TestCase
{
    public function test_it_resolves_any_persisted_authenticated_billable_model(): void
    {
        $model = new SubscribableTestUser;
        $model->id = 15;
        $model->name = 'Customer';
        $model->exists = true;

        $request = Request::create('/checkout', 'POST');
        $request->setUserResolver(fn (): User => $model);

        $this->assertSame($model, (new AuthenticatedBillableResolver)->resolve($request));
    }

    public function test_it_rejects_a_guest_checkout(): void
    {
        $request = Request::create('/checkout', 'POST');
        $request->setUserResolver(fn () => null);

        $this->expectException(AuthorizationException::class);
        (new AuthenticatedBillableResolver)->resolve($request);
    }

    public function test_it_rejects_unsaved_billable_models(): void
    {
        $model = new SubscribableTestUser;
        $request = Request::create('/checkout', 'POST');
        $request->setUserResolver(fn () => $model);

        $this->expectException(AuthorizationException::class);
        (new AuthenticatedBillableResolver)->resolve($request);
    }

    public function test_it_rejects_a_user_without_subscription_trait(): void
    {
        $model = new User;
        $model->id = 15;
        $model->exists = true;
        $request = Request::create('/checkout', 'POST');
        $request->setUserResolver(fn () => $model);

        $this->expectException(AuthorizationException::class);
        (new AuthenticatedBillableResolver)->resolve($request);
    }
}
