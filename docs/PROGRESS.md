# Subbase Payment — development progress

Date: 2026-10-29
- [x] Fork identified: `fnx-software/subbase-payment`; upstream `nafiswatsiq/subbase-payment`.
- [x] Branch: `feat/universal-payment-foundation`, isolated from `main`.
- [x] Native Laravel Gate fail-closed helper and explicit policy actor.
- [x] `ResolvesCheckoutBillable` / default authenticated user resolver; checkout can resolve custom persisted Eloquent billable.
- [x] Paid checkout metadata includes billable morph type/key; legacy user metadata retained.
- [x] Suppress destructive Payment/Webhook Log UI actions.
- [x] Stripe async-status check; minimal retained webhook diagnostic metadata and after-commit event dispatch.
- [x] Test source and Testbench dependency committed; **verified passing in the 2026-10-29 CI run**.
- [x] [Green CI](https://github.com/fnx-software/subbase-payment/actions/runs/40828948348): 36 PHPUnit tests / 58 assertions; Composer validation, scoped Pint and Composer audit succeeded.
- [ ] Unique processed webhook event ledger with merchant/tenant scope.
- [ ] Full verified paid subscription activation and owner isolation.
- [ ] Exact decimal prices / currency scales.
- [ ] Reconciliation, refunds, disputes and retry-safe subscription cycle.
- [ ] Gateway integration with verified sandbox + live account.
- [ ] Stable `fnx-software/*` Composer package names, tags and upgrade manual.
- [ ] Multi-tenant Filament resources query-scoped, role/policy integration, cross-tenant tests.

## Continued development

Read `docs/UNIVERSAL-PAYMENTS.md` and `docs/PROGRESS.md` before implementation. Keep package independent of Fenix. Update tests, changelog and progress on every PR. Do not merge this branch into main until compatibility and security tests have passed.

## CI style debt

The upstream payment fork has 41 pre-existing Laravel Pint formatting violations across about 60 PHP files. The current workflow only gates new secure helper/contract/test files. A dedicated no-logic formatting PR must eliminate this baseline before a stable major release.

## Review

- [Draft pull request #2](https://github.com/fnx-software/subbase-payment/pull/2) — branch remains separate from `main` pending consumer integration, full financial verification and release preparation.

- [x] Extracted `CheckoutBillableManager` from the controller for independent consumer testing, with business-owner Gate fixture. Verified in CI (including authorized and unauthorized non-User Eloquent billable test cases).
