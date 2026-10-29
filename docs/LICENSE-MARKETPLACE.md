# Reseller License Marketplace — API and payment flow

**Development only.** The one-time key and Provider business rules are implemented in the sibling `fnx-software/subbase` package. This package exposes the authenticated marketplace endpoints and **ties key issuance to verified payment settlement**. Do not operate with production merchant credentials until the release blockers below are closed.

## Overview

For Fenix CRM Individual Annual:
- Fenix retail price: **USD 150** per key.
- Approved Provider with wholesale MOQ **12** pays **USD 100** × 12 = **USD 1,200**.
- The Provider may independently sell each key to a customer at **USD 140**, retaining gross spread **USD 40** per sold key, before fees and taxes.
- The customer saves USD 10 compared with official retail. The Provider collects customer payment by their own method and optionally records the sale for analytics.

A wholesale purchase does **not** activate the reseller's CRM subscription. Only a customer with an authorized individual/organization account who redeems an unused key receives the annual `license-crm` subscription, starting when redeemed. The business constraints and secure token data are described in [Subbase's reseller spec](https://github.com/fnx-software/subbase/blob/feat/universal-billing-foundation/docs/LICENSING-RESELLERS.md).

## Authentication and routes

All marketplace routes use Laravel `web` + `auth` and rate limiting. Fenix Accounts / ZITADEL integration should terminate in the host application's Laravel authenticated session. This package is **not** itself an identity provider and must not accept an external `user_id`/organization key as proof of ownership.

```text
GET  /subbase-payment/licenses/offers                 # active retail offers only
POST /subbase-payment/licenses/providers              # provider application, pending review
GET  /subbase-payment/licenses/providers/prices       # approved reseller wholesale prices
GET  /subbase-payment/licenses/providers/dashboard    # own reseller inventory/gross margin
POST /subbase-payment/licenses/orders                 # server-priced retail/wholesale checkout
GET  /subbase-payment/licenses/orders/{order}         # authorized payment/order status
GET  /subbase-payment/licenses/orders/{order}/keys    # authorized paid inventory export
POST /subbase-payment/licenses/keys/{key}/resale       # own independent resale declaration
POST /subbase-payment/licenses/redeem                  # customer activates code
```

All POSTs are CSRF-protected by `web` middleware, in addition to Laravel Gates. The buyer's `CheckoutBillableManager` must be configured for the actual eligible user or organization and must enforce tenant membership. Define the matching Gates in the host: `applyForLicenseProvider`, `purchaseWholesaleLicenses`, `purchaseRetailLicense`, `viewLicenseOrder`, `viewPurchasedLicenseKeys`, `recordLicenseResale`, `redeemLicenseKey`, `viewLicenseProviderDashboard`; admin Gates `manageLicenseProviders`, `manageLicenseCatalog`, `viewLicenseOrders` belong to Subbase/Filament.

## Request examples

Provider application (no automatic approval):

```http
POST /subbase-payment/licenses/providers
Content-Type: application/json

{"business_name":"Electronics Store"}
```

Admin approves through the **License Providers** Filament resource (server-side `LicenseProviderService` authorization).

An approved Provider can see wholesale offers and request a quantity:

```http
GET /subbase-payment/licenses/providers/prices

POST /subbase-payment/licenses/orders
Content-Type: application/json

{
  "offer_id": 1,
  "provider_id": 1,
  "quantity": 12,
  "idempotency_key": "shop-order-20261029-001",
  "name": "Electronics Store",
  "email": "accounts@example.invalid"
}
```

The IDs in this example are **illustrative placeholders**. They cannot override the authenticated purchaser association or an authorized Provider's ownership. The resulting checkout URL is from the configured payment gateway, never a client-supplied URL. Server snapshot amount and currency are persisted with intent; retrying the same checkout is blocked from charging twice.

After webhook fulfillment, Provider can export available inventory and record its own receipts:

```http
GET /subbase-payment/licenses/orders/{uuid}/keys

POST /subbase-payment/licenses/keys/{key-id}/resale
Content-Type: application/json

{"sale_price":"140.00","reference":"SHOP-RECEIPT-101"}
```

The `{key-id}` is the internal record ID only for logging the declared resale; the plaintext key is exported from the authorized inventory endpoint. The resale endpoint does not charge an end customer, confirm the sale's independent settlement, or grant access. No-storing/caching protections are applied to inventory/key responses.

Customer later activates their own entitlement:

```http
POST /subbase-payment/licenses/redeem
Content-Type: application/json

{"license_key":"LIC-<the-actual-random-key-from-authorized-provider>"}
```

The server binds activation to the **authenticated, authorized** destination account from the application's billable resolver. Invalid/already used keys fail; the code is wiped from encrypted inventory after redemption, but the audit record stays.

## Authoritative payment state machine

1. `LicenseOrderService::place` snapshots product, plan, term, prices, buyer/reseller and units; creates status `pending`.
2. `LicenseCheckoutService::begin` locks the order and transitions to `checkout_started`, makes a real provider `charge` request, persists `subscription_payments` intent with `purchase_type=license_order`, then transitions the order to `awaiting_payment`.
3. Only the **verified provider webhook** enters `VerifiedPaymentProcessor`, which locks the matching payment and uniquely records the provider event.
4. It validates provider-confirmed paid amount and currency against persisted intent, and `PaidLicenseOrderFulfillment` checks buyer polymorphic identity, reseller, offer, order and total amount against the original order snapshot.
5. Inside the same database transaction, `LicenseFulfillmentService` issues exactly the quantity of unique 128-bit encrypted keys, marks the order `fulfilled`, payment `paid` and payment reference. The processor emits `LicenseOrderFulfilled` **after commit**, not `PaymentReceived`, so it does not accidentally subscribe the reseller.
6. Replaying an event or sending another paid event for the same payment cannot generate extra keys.
7. Customer uses their key later to create/extend the product subscription from the activation date.

If a gateway request times out after checkout started, the order remains in a reconcile-required state; DO NOT automatically create a new charge until verifying whether the gateway already has a session. Webhooks received before the locally committed intent may return a retryable error.

## Operational security

- Mandatory HTTPS, secure authentication, tenant-aware Gate policies, CSRF, passwordless/OIDC host hardening and secrets rotation.
- Keys are bearer secrets until redemption; do not put them in logs, provider metadata, publicly cacheable JSON, emails without appropriate delivery/security, or analytics.
- Provider has no price-editing permission. Fenix admin chooses official retail/wholesale and eligibility. The default maximum resale price is configurable, subject to **applicable competition and reseller-pricing law**.
- A store's reseller revenue is **self-reported**; record net refunds, processing/tax costs separately when financially relevant.
- App permissions and entitlement synchronization with CRM/POS/Invoice require a Fenix-side central billing adapter (not provided by the generic package).
- A connected central billing service is required: a code issued in one isolated database cannot be redeemed in a different isolated project database.
- Refunds, chargebacks, stolen-key reissue, multi-merchant credentials, provider tier pricing, bank transfer settlement, dunning and product-level feature seat enforcement are still unimplemented.
- Real provider sandbox and PostgreSQL/MySQL integration testing is pending. No live production readiness or stable package release is claimed.

## Testing

`tests/Licensing/LicensePaymentTest.php` exercises a fake payment gateway from server-priced 12-license purchase, webhook match, issuing exactly 12 keys once, no reseller subscription, mismatched amount rollback and reseller metadata tampering. Tests do not equate to a complete live Stripe/PayPal/Paddle/Midtrans/Xendit sandbox audit.
