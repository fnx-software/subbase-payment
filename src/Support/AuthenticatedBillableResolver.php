<?php

declare(strict_types=1);

namespace Nafiswatsiq\SubbasePayment\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Nafiswatsiq\SubbasePayment\Contracts\ResolvesCheckoutBillable;

/**
 * Backwards-compatible default: charge the authenticated user's subscription.
 */
final class AuthenticatedBillableResolver implements ResolvesCheckoutBillable
{
    public function resolve(Request $request): Model
    {
        $billable = $request->user();

        if (! $billable instanceof Model
            || ! $billable->exists
            || ! method_exists($billable, 'planSubscriptions')
            || ! method_exists($billable, 'newPlanSubscription')
        ) {
            throw new AuthorizationException('An eligible authenticated subscription owner is required.');
        }

        return $billable;
    }
}
