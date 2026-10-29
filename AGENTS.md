# Subbase Payment contributor instructions

Purpose: standalone, reusable **subscription** payments for any Laravel project. Core must not depend on Fenix Accounts, ZITADEL or any fixed tenancy/user schema.

1. Read `docs/UNIVERSAL-PAYMENTS.md`, `docs/BACKLOG.md`, `docs/PROGRESS.md`, and the Subbase fork product spec.
2. Check installed Laravel, PHP, Filament, gateway SDK and upstream version before any code/API change.
3. Extend `Contracts/ResolvesCheckoutBillable` via host app authorization, or use default authenticated user; never trust posted organization IDs.
4. Every gateway event must be signed, uniquely deduplicated by merchant/provider/event and matched to a persisted amount, currency and owner before activation.
5. Use decimal-safe calculations and explicit ISO currency scales; never mark paid from frontend redirects.
6. Use transactions, locks, post-commit events and retry-safe idempotent actions. Do not overwrite financial history or delete payment/audit records.
7. Keep Filament resources behind explicit Gate/record policies and tenant-scoped queries, including ViewAny/filters/bulk actions.
8. Preserve MIT license, Composer name and namespaces until documented breaking release migration; keep original maintainer credit.
9. Every code change requires regression tests (not just mocks), lint, dependency audit, provider sandbox and updated `docs/PROGRESS.md` + Changelog.
10. Keep PR draft until full legacy consumer verification. Do not silently merge to `main` or deploy to customer systems.
