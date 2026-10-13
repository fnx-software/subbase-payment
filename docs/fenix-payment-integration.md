# Fenix Accounts: payment integration contract

This package handles checkout, provider events and payment persistence. It must **not** decide which organization a user may administer or which Fenix product can be used.

## Ownership

- Every checkout must use a server-authorized organization and an explicit application/plan selection.
- The server must determine amount, currency and payee from a trusted plan, never from client form fields.
- Payment completion redirects are informational; only authenticated, verified provider webhooks (or provider-verified server-side reconciliation) may activate a paid subscription.
- Subscription activation is delegated to Fenix Accounts/Subbase after the payment event is durably recorded.

## Mandatory verification before production

1. Verify signatures using provider-documented raw payload and secrets. Reject unverified callbacks.
2. Persist unique provider event IDs, and ensure duplicate/delayed webhook retries do not double-activate subscriptions.
3. Match provider transaction ID, expected currency/amount, organization and plan before activation.
4. Design refunds, chargebacks, cancel-at-period-end and out-of-order webhook handling.
5. Test cross-organization access denials for checkout, payment history and invoice routes.
6. Do not log credentials, full payment details or sensitive webhook signatures.
7. Protect public checkout from open redirects and CSRF issues; verify webhook routes separately.
8. Use gateway sandbox/test mode and PostgreSQL migration tests before enabling any live keys.

The README documents available gateways; it is **not** a substitute for provider-by-provider webhook security review.

## Dependency note

The Composer package name remains `nafiswatsiq/subbase-payment`. This fork depends on `nafiswatsiq/subbase`. Ensure the parent Subbase dependency resolves to the intended Fenix fork via locked Composer sources before installation.
