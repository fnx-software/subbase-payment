# FNX Subbase Payment — development progress

Updated: 2026-10-29. **Draft PR:** https://github.com/fnx-software/subbase-payment/pull/2.

## Product contract

This is the **only payment companion** for Subbase subscription billing in independent and Fenix-related Laravel projects. No Fenix Account dependency. One project may use the two Composer packages locally, or a central billing service may use the same engine and expose optional APIs. Application-specific POS/Invoice/School customer payment ledgers remain separate.

## Built and verified on feature branch

- [x] Composer package identity is `fnx-software/subbase-payment` in the development branch; existing PHP namespace is unchanged for source compatibility.
- [x] Cross-fork CI uses the actual development copy of `fnx-software/subbase` through a local path Composer repository rather than the original upstream version.
- [x] Model-agnostic `ResolvesCheckoutBillable` plus `CheckoutBillableManager`, authenticated User default and explicit School/Organization host resolver.
- [x] Enforced fail-closed Gate permissions, including tenant-aware Filament transaction/log query, route and record guards, when the optional `PaymentTenantAccess` resolver is enabled.
- [x] Exact BCMath `GatewayMoney` ISO currency-scale conversions and widened subscription payment decimal column.
- [x] Paid-intent billable type/key, allowlisted owner types, DB-row locking, signed-webhook-only subscription activation. Browser return never marks an invoice paid.
- [x] Durable processed-event ledger unique by driver/account/event ID; replay does not activate subscription twice in the tested single-account deployment configuration.
- [x] Settlement requires amount and currency matching the stored intent. Provider raw body/authorization headers are not stored in audit logs.
- [x] Stripe async checkout status semantics and request signing, Paddle timestamp replay protection, PayPal verification excludes internal artificial data. Added amount/currency fields to gateway events.
- [x] Payment logs may not be deleted from Filament; Payments and Events are append-oriented.
- [x] `subbase-payment:install` no longer generates a separate legacy activation listener; existing manual registrations must be reviewed on upgrade.
- [x] First SQLite integration tests for a paid Organization, replay, wrong billable, amount mismatch, money scales and schema migration.
- [x] [Green compatible cross-fork CI](https://github.com/fnx-software/subbase-payment/actions/runs/40838975376) with Composer validation, PHPUnit, scoped Pint and Composer audit. Earlier green run with current financial features: 48 tests / 100 assertions.

## Not finished / production blockers

- [ ] Test five gateway SDK/event variants and merchant accounts in real provider sandboxes; Stripe has signature/amount fixtures but no completed merchant checkout.
- [ ] Full merchant-account-level transaction ownership and tenant-specific credentials with secret rotation; processed-events currently use one configured merchant account per driver (default).
- [ ] Actual refund, partial refund, dispute/chargeback, payout settlement and reconciliation ledger.
- [ ] Multi-gateway recurring mandates, renewal job orchestration, failed charge dunning, retry/backoff and double-charge concurrency tests.
- [ ] Additional event family fixtures (PayPal, Paddle, Midtrans, Xendit); incomplete authoritative amounts are intentionally rejected for paid settlement.
- [ ] Real-world app / PostgreSQL / MySQL migration and end-to-end compatibility verification; tests currently use SQLite.
- [ ] Complete host-app integration, Filament and payment form authorization, two-tenant job/export/row-level isolation.
- [ ] Full legacy Pint cleanup (41 original style issues), supported version matrix and tagged stable release.
- [ ] Confirm direct `composer require fnx-software/*` resolves after coordinated default-branch rename and release; existing `main` is not changed.
- [ ] Migration test + backup for existing CRM and Zeno customer/merchant and subscription records.

## CI/distribution warning

The default branch of the companion package still carries its old Composer name. **Until coordinated merges/releases**, the payment CI clones the matching Subbase feature branch to `_packages/subbase` and injects a Composer path repository. It does not prove installation from Packagist. Do not use the feature branch with live payment credentials or replace the active CRM/Zeno dependency.

## Continuation

Read `AGENTS.md`, `docs/INSTALLATION.md`, `docs/BACKLOG.md`, `docs/PROGRESS.md`, `docs/UNIVERSAL-PAYMENTS.md`; track all changes with tests and `Changelog.md`. Draft PR remains open until finance/legal/security gates are verified.

### Most recent verified webhook state-machine check

- [x] Added a regression test ensuring a delayed `pending` gateway notification cannot reopen a `failed` payment. A later *properly verified* paid event can still settle the same intent.
- [x] The cross-fork CI run [40838975376](https://github.com/fnx-software/subbase-payment/actions/runs/40838975376) passed **48 tests / 100 assertions**, Composer validate/update/audit and targeted Pint. This is not a production gateway sandbox certification.
