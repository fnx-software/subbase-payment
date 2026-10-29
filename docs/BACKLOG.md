# Payment platform backlog (Issues disabled on this GitHub fork)

Last updated: 2026-10-29. Active draft PR: https://github.com/fnx-software/subbase-payment/pull/2.

## P0 — Verified ownership and paid activation

- [ ] Persist billable owner and payment intent with immutable plan, amount, currency and merchant snapshots.
- [ ] Verified paid webhook activates/renews/switches the **resolved User/Organization/School** exactly once.
- [ ] Restrict billable model classes to an approved morph map. Do not blindly instantiate classes from metadata.
- [ ] Ensure a configured CheckoutBillableManager is reused by CheckoutController, gateway event and activation actions.
- [ ] Full tenant authorization for checkout, logs, payments, callback and manual actions.

## P0 — Event security and accurate finance

- [ ] Atomic processed webhook ledger keyed by `gateway + merchant + event ID`.
- [ ] Compare amount/currency/account with persisted intent and authoritative provider response.
- [ ] Replace all float/round-to-two-decimals assumptions with exact currency-sensitive calculations.
- [ ] Refunds, partial refunds, disputes/chargebacks, gateway retries and reconciliation.
- [ ] No raw headers/auth secrets in logs and immutable money audit; configurable retention.

## P1 — Production integrations

- [ ] Sandbox tests for Stripe, PayPal, Paddle, Midtrans, Xendit and custom gateway contract.
- [ ] Per-tenant merchant credentials in encrypted storage/secret manager.
- [ ] Recurring authorized mandates, scheduled renewals, dunning, notifications and user self-service.
- [ ] Accurate customer receipts, fiscal tax validations, ar/en/fr and further locales.
- [ ] Full Pint baseline cleanup (41 upstream issues), PHP/Laravel/Filament CI matrix, semver and package migration.
- [ ] Independent standalone demo and safe upgrade of CRM/Zeno.

The branch is a prototype; no live payment provider or automatically completed subscription lifecycle is asserted.
