<?php

declare(strict_types=1);

namespace Nafiswatsiq\SubbasePayment\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Nafiswatsiq\SubbasePayment\Contracts\ResolvesCheckoutBillable;
use Nafiswatsiq\SubbasePayment\Exceptions\PaymentConfigurationException;
use Nafiswatsiq\SubbasePayment\Support\AuthenticatedBillableResolver;

/**
 * Single checkout entry point for standalone users and business subscribers.
 * The configured resolver MUST independently authorize the actor's access.
 */
final class CheckoutBillableManager
{
    public function resolve(Request $request): Model
    {
        $resolverClass = config(
            'subbase-payment.checkout.billable_resolver',
            AuthenticatedBillableResolver::class,
        );

        if (! is_string($resolverClass)
            || ! is_a($resolverClass, ResolvesCheckoutBillable::class, true)
        ) {
            throw new PaymentConfigurationException('Checkout billable resolver is invalid.');
        }

        $billable = app($resolverClass)->resolve($request);

        if (! $billable->exists
            || $billable->getKey() === null
            || ! method_exists($billable, 'planSubscriptions')
            || ! method_exists($billable, 'newPlanSubscription')
        ) {
            throw new PaymentConfigurationException('Billable model must be persisted and support subscriptions.');
        }

        return $billable;
    }
}
