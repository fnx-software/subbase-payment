# FNX Subbase Payment — standalone and organization installation

**Development-only** (2026-10-29). The current implementation has not passed payment-provider sandbox certification or multi-merchant tests. Do not use for live payments.

## Local development installation with both FNX forks

The canonical package identities in the feature branches are `fnx-software/subbase` and `fnx-software/subbase-payment`. To avoid mixing the upstream default branches during testing, clone both repositories in a **clean sandbox Laravel project**:

```bash
mkdir -p packages
git clone --branch feat/universal-billing-foundation https://github.com/fnx-software/subbase.git packages/subbase
git clone --branch feat/universal-payment-foundation https://github.com/fnx-software/subbase-payment.git packages/subbase-payment

composer config repositories.fnx-subbase --json '{"type":"path","url":"packages/subbase","options":{"symlink":true,"versions":{"fnx-software/subbase":"dev-feat/universal-billing-foundation"}}}'
composer config repositories.fnx-subbase-payment --json '{"type":"path","url":"packages/subbase-payment","options":{"symlink":true,"versions":{"fnx-software/subbase-payment":"dev-feat/universal-payment-foundation"}}}'

composer require 'fnx-software/subbase:dev-feat/universal-billing-foundation' 'fnx-software/subbase-payment:dev-feat/universal-payment-foundation' -W
php artisan subbase:install
php artisan subbase-payment:install --driver=stripe
php artisan migrate
```

Use a **testing-only database**. Ensure PHP8.4+, `ext-bcmath`, Filament5, PostgreSQL or local SQLite and Composer2. Provider keys are not needed for unit tests; for real gateway sandbox tests use legitimate sandbox credentials in uncommitted secrets.

The installer writes gateway configuration but no longer generates `app/Listeners/ActivateSubbaseSubscription.php`. If you previously installed a version that generated that listener, inspect and **unregister it** before using the internal verified activation flow. Do not blindly delete application customizations.

## Organization/School billables

Any Eloquent model may own subscriptions through `Laravelcm\Subscriptions\Traits\HasPlanSubscriptions`; payment checkout resolves the owner using an application-specific implementation:

```php
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Nafiswatsiq\SubbasePayment\Contracts\ResolvesCheckoutBillable;

final class AuthorizedOrganizationResolver implements ResolvesCheckoutBillable
{
    public function resolve(Request $request): Model
    {
        $actor = $request->user();
        $organization = $actor?->currentOrganization;

        if (!$actor || !$organization || !$actor->can('manageBilling', $organization)) {
            throw new AuthorizationException();
        }

        return $organization;
    }
}
```

In the host application's `config/subbase-payment.php`:

```php
'checkout' => [
    'billable_resolver' => \App\Billing\AuthorizedOrganizationResolver::class,
    'billable_models' => ['organization' => \App\Models\Organization::class],
    'subscription_name' => 'default',
    // retain other configuration options from the published package
],
```

**Important:** If your Eloquent model uses a custom morph map, configure the exact alias/class that maps to its `getMorphClass()`; never infer or instantiate a PHP model class from client input.

## Payment lifecycle

1. An authenticated actor with explicit tenant/organization membership selects a plan, billing currency and verified payer.
2. Checkout resolves the Eloquent owner through `ResolvesCheckoutBillable`, snapshots intent amount/currency/plan and calls a configured gateway.
3. The browser is redirected to the hosted provider. A success URL is **not** authorization or proof of payment.
4. The provider webhook is signature-verified, then `VerifiedPaymentProcessor` identifies the stored transaction and locks its payment row.
5. A unique event is recorded. Paid settlement requires an exact amount and currency match; `PaidSubscriptionActivator` creates or renews the authorized subscriber's plan in the same DB transaction. Replay is ignored.
6. A `PaymentReceived` event is dispatched after commit; optional notifications can be queued.

Provider-specific refunds/chargebacks and recurring mandates still need implementation and sandbox certification.

## Tenant isolation

Default is standalone/local checkout. To enable tenant isolation for Filament payment logs/records, configure:

```php
'tenancy' => [
    'enabled' => true,
    'access' => \App\Billing\PaymentTenantAccess::class,
],
```

The class must implement `Nafiswatsiq\SubbasePayment\Contracts\PaymentTenantAccess`, providing `scope(Builder $query, Authenticatable $actor)` and `allows(?Model $record, string $ability, Authenticatable $actor)`. If enabled but unresolved, access is denied. This contract is not a substitute for host-owned API authorization, queue scopes, and server-side invoice ownership checks.

## Testing + release

In each fork's checkout: `composer validate --strict`, `composer audit`, `vendor/bin/phpunit`. GitHub CI uses the matching Subbase fork via a Composer local path. Full Pint is not yet green for upstream legacy source, though scoped CI is.

Do **not** tag as stable, merge to main, use live merchant credentials, or upgrade CRM/Zeno until full production gates in `docs/PROGRESS.md` pass.
