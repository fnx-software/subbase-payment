# Universal subscription payments — fork architecture

Status: initial security and billable customization branch. No live payment processing should use this branch without sandbox + end-to-end tests.

## Independence first

- Subbase Payment must not depend on Fenix Accounts, ZITADEL, a specific Company, School or User model.
- Keep the current subscription-plan payment flow for backwards compatibility; gate it with verified checkout ownership, merchant context and idempotency.
- Payment gateway drivers are optional adapters. Allow one application to install Subbase for plans alone, or Subbase + Subbase Payment for integrated checkout.
- A generic one-off Invoice/POS payment engine must not be conflated with subscriber plan charges. Extract a gateway kernel only after the contracts have been tested across both use cases.
- For central SaaS billing, use a central Laravel billing service and optional API adapters; for independent products, install packages locally.

## New billable resolver contract

A host app can implement `Nafiswatsiq\SubbasePayment\Contracts\ResolvesCheckoutBillable` and configure `subbase-payment.checkout.billable_resolver`. It returns an authorized, persisted Eloquent model using `HasPlanSubscriptions`.

Default is `AuthenticatedBillableResolver`, which resolves the authenticated user (legacy behavior). Organization, School, Tenant and Project checkout are possible without Fenix-specific classes.

```php
<?php

namespace App\Billing;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Nafiswatsiq\SubbasePayment\Contracts\ResolvesCheckoutBillable;

final class CheckoutSchoolResolver implements ResolvesCheckoutBillable
{
    public function resolve(Request $request): Model
    {
        $school = $request->user()?->currentSchool;

        if (! $school || ! $request->user()->can('manageBilling', $school)) {
            throw new AuthorizationException();
        }

        return $school;
    }
}
```

In `config/subbase-payment.php`:

```php
'checkout' => [
    'billable_resolver' => \App\Billing\CheckoutSchoolResolver::class,
    // existing options...
],
```

**Current limit:** free-plan creation now uses the resolved billable model. Paid checkout records `billable_type` and `billable_id` in metadata while retaining `user_id` for callback compatibility. A full verified paid-subscription activation listener and lifecycle upgrade are still required, and must never resolve an arbitrary remote model class from user-controlled metadata. Prefer configured morph maps. No tenant isolation is implied by a UI permission.

## Implemented hardening

- Fail-closed Laravel Gate-based permissions, with optional Spatie.
- Financial payment and webhook log resources refuse destructive UI deletes.
- Stripe's `checkout.session.completed` only settles a payment when `payment_status=paid`; async success is supported.
- Gateway webhook receives the original signed payload; persisted diagnostic logs contain only selected metadata, never raw HTTP headers/bodies.
- `PaymentReceived` is dispatched after the transaction commits (subject to duplicate-event race protections below).

## Required before production

- Replace price/discount float math in Subbase with decimal-safe money objects and currency scales.
- Atomic unique processed event ledger: `(gateway, merchant, event_id)`. Current single `webhook_event_id` field on a payment does not preserve all historical events.
- Verify merchant account, billable ownership, amount, currency and transaction status in each gateway webhook. Reject forged or cross-tenant status updates.
- Idempotent subscription activation, switch/renewal, refunds, disputes and chargebacks.
- Restricted webhook log retention, encryption / redaction, provider backoff and queue failures.
- Per-tenant merchant credentials via secret manager, provider availability rules, multi-currency settlement and reconciliation.
- Fully tested, supported recurring charging (requires gateway-specific mandate and policy) and dunning.
- Ensure recorded transactions cannot be edited or deleted through direct application actions.

## Distribution

Like the Subbase fork, this branch retains the existing `nafiswatsiq/subbase-payment` Composer identity and PHP namespace until a coordinated major migration to a `fnx-software/*` release is tested. Preserve MIT credits, APIs, existing routes and migrations during transition.

## Tests and status

Added Testbench/PHPUnit tests for Gate authorization, the authenticated billable resolver and Stripe paid vs pending webhook status. They need a successful CI run; no gateway sandbox integration has yet been executed.
