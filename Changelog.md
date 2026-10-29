# Changelog

## Unreleased (2026-10-29): universal subscription payments foundation

- Introduced opt-in model-agnostic billable resolver (defaults to the authenticated user).
- Removed allow-all permission fallback when Spatie is absent; checks use Laravel Gate.
- Disallowed destructive Filament actions on financial payment and webhook log resources.
- Fixed Stripe checkout completion interpretation for asynchronous payment methods.
- Removed sensitive raw webhook/header retention from diagnostic log and moved `PaymentReceived` after transaction commit.
- Added Testbench unit tests and documented production blockers.

**Breaking behavior:** environments without explicit Laravel Gate / Spatie ability grants now receive access denials. Do not deploy to existing customers without reviewing roles. Paid organization subscriptions are not yet automatically activated end-to-end.
