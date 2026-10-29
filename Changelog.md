# Changelog

## Unreleased (2026-10-29): universal subscription payments foundation

- Introduced opt-in model-agnostic billable resolver (defaults to the authenticated user).
- Removed allow-all permission fallback when Spatie is absent; checks use Laravel Gate.
- Disallowed destructive Filament actions on financial payment and webhook log resources.
- Fixed Stripe checkout completion interpretation for asynchronous payment methods.
- Removed sensitive raw webhook/header retention from diagnostic log and moved `PaymentReceived` after transaction commit.
- Added Testbench unit tests and documented production blockers.

**Breaking behavior:** environments without explicit Laravel Gate / Spatie ability grants now receive access denials. Do not deploy to existing customers without reviewing roles. Paid organization subscriptions are not yet automatically activated end-to-end.

## 2026-10-29 — Verified paid settlement, exact money and FNX distribution

- Adopted development Composer name `fnx-software/subbase-payment`, with `fnx-software/subbase` as the exact compatible development dependency. PHP namespace remains upstream for source compatibility.
- Added exact `GatewayMoney` arithmetic, multi-currency amount matching, four-decimal upgrade migration and PostgreSQL-safe unique processed event schema.
- Added `PaidSubscriptionActivator` and `VerifiedPaymentProcessor` to fulfill paid organization/user subscriptions exactly once after a signed and matched provider event. Removed redirect-based paid fulfillment.
- Added optional tenant-aware payment Filament view/query controls; host applications implement their own access resolver.
- Improved Stripe async events and provider reconciliation metadata, reduced stored webhook secrets and introduced Paddle replay timeout.
- Removed installer-generated activation listener; host upgrades must unregister any previously installed copy before using new processing.
- Tested 47 cases / 95 assertions in SQLite before the Composer package-name migration; [compatible cross-fork CI](https://github.com/fnx-software/subbase-payment/actions/runs/40838758878) is green.
- Added `docs/INSTALLATION.md` and strict release gates.

**Still in Draft PR:** No real gateway sandbox captures, refunds, dispute reconciliation, multi-merchant secrets, production database tests, stable tags or verified CRM/Zeno upgrades.

### Payment status ordering

- Prevented stale pending/approved provider events from reopening terminal failed/canceled payments; added regression test. Signed paid events are still eligible to settle a delayed capture. Cross-fork CI passed 48 tests / 100 assertions.
