<?php

declare(strict_types=1);

namespace Nafiswatsiq\SubbasePayment\Tests\Checkout;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Laravelcm\Subscriptions\Traits\HasPlanSubscriptions;
use Nafiswatsiq\SubbasePayment\Contracts\ResolvesCheckoutBillable;
use Nafiswatsiq\SubbasePayment\Exceptions\PaymentConfigurationException;
use Nafiswatsiq\SubbasePayment\Services\CheckoutBillableManager;
use Nafiswatsiq\SubbasePayment\Tests\TestCase;

final class BusinessSubscriberModel extends Model
{
    use HasPlanSubscriptions;
}

final class AuthorizedBusinessResolver implements ResolvesCheckoutBillable
{
    public function resolve(Request $request): Model
    {
        $business = app('test.business');

        if (! Gate::forUser($request->user())->allows('subscribe-to-business', $business)) {
            throw new AuthorizationException('You cannot subscribe for this business.');
        }

        return $business;
    }
}

final class CheckoutBillableManagerTest extends TestCase
{
    public function test_it_accepts_a_non_user_business_with_authorized_membership(): void
    {
        $business = new BusinessSubscriberModel;
        $business->id = 11;
        $business->exists = true;
        app()->instance('test.business', $business);

        $owner = new User;
        $owner->name = 'owner';

        Gate::define('subscribe-to-business', fn (User $actor, Model $record): bool => 
            $actor->name === 'owner' && $record->getKey() === 11);

        Config::set('subbase-payment.checkout.billable_resolver', AuthorizedBusinessResolver::class);

        $request = Request::create('/checkout', 'POST');
        $request->setUserResolver(fn (): User => $owner);

        $resolved = (new CheckoutBillableManager)->resolve($request);

        $this->assertSame($business, $resolved);
    }

    public function test_it_rejects_cross_tenant_actor(): void
    {
        $business = new BusinessSubscriberModel;
        $business->id = 11;
        $business->exists = true;
        app()->instance('test.business', $business);

        $actor = new User;
        $actor->name = 'outsider';

        Gate::define('subscribe-to-business', fn (User $user, Model $record): bool =>
            $user->name === 'owner' && $record->getKey() === 11);

        Config::set('subbase-payment.checkout.billable_resolver', AuthorizedBusinessResolver::class);

        $request = Request::create('/checkout', 'POST');
        $request->setUserResolver(fn (): User => $actor);

        $this->expectException(AuthorizationException::class);
        (new CheckoutBillableManager)->resolve($request);
    }

    public function test_it_rejects_non_resolver_class_configuration(): void
    {
        Config::set('subbase-payment.checkout.billable_resolver', \stdClass::class);

        $this->expectException(PaymentConfigurationException::class);

        (new CheckoutBillableManager)->resolve(Request::create('/checkout', 'POST'));
    }
}
