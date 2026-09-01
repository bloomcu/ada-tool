# `customer_reviewable` flag — usage & operations

> **Disposable / first-pass.** A coarse "review this first" flag on `pages` that marks
> pages carrying a **customer / CMS-editable** issue (headings, link text, alt text,
> tables) so a client can triage them before the real classifier exists. It errs toward
> flagging on purpose (under-flagging a real editor issue is worse than over-flagging).
> **Remove it when the classifier lands** (see removal section).

## How it's computed (and why it stays in sync)
Derived from `page.results` by `DDD\Domain\Pages\CustomerEditableRules::reviewable()`. It is
**not** maintained by a background job — it is written in the *same places the page counts
are written*, so it can never drift from the results:

- `ScanImportController@import` — bulk scan import
- `ScanImportController@importPage` — single-page rescan (overwrites the page in place)

Because a single-page rescan recomputes it alongside `violation_count` / `warning_count`,
there is nothing separate to keep in sync.

**Rules deliberately excluded** (they would over-flag): `CONTROL_4` ("buttons need visible
text" — in real scans it's the site-wide notice banner / Google Maps controls, often on
*every* page → would flag the whole site) and `CONTROL_10` (form/plugin labels).

## Index ordering
`ScanResource` sorts **`customer_reviewable` DESC first**, then worst-first by violation /
warning count, so flagged pages float to the top of a scan's page list. (MySQL sorts NULL
last on DESC, so not-yet-backfilled pages sink to the bottom.)

## Backfill command
The migration adds the **column only** — it does *not* backfill, because decoding every
page's ~117 KB `results` inline would make the deploy migration a memory / time hazard.
Existing pages stay `NULL` until re-scanned, or backfill them explicitly:

```bash
php artisan pages:backfill-reviewable              # every NULL page
php artisan pages:backfill-reviewable --scan=1909  # just one scan (good for a spot-check first)
php artisan pages:backfill-reviewable --chunk=100  # smaller batches (default 200)
```

Properties:
- **Resumable** — only touches rows where `customer_reviewable IS NULL`, so re-running or
  interrupting mid-run is safe; already-done rows are skipped.
- **Memory-bounded** — `chunkById`, selecting only `id` + `results`.
- **Non-invasive** — writes via the query builder (no `updated_at` churn, no model events).

## Deploy sequence
1. `php artisan migrate` — fast, column only.
2. `php artisan pages:backfill-reviewable` — when you want existing scans lit up. Safe to
   run off-peak; use `--scan=<id>` to stage it (e.g. verify one scan before the full run).

New and rescanned pages get the flag automatically going forward — the backfill is a
one-time catch-up for pages scanned **before** this shipped.

## Removal (when the real classifier replaces this)
Delete, in one PR:
- `app/Domain/Pages/CustomerEditableRules.php`
- `app/App/Console/Commands/BackfillCustomerReviewable.php`
- the two `customer_reviewable => CustomerEditableRules::reviewable(...)` lines in
  `ScanImportController` (`import` + `importPage`)
- the `customer_reviewable` entry in `ScanResource`'s `select()` and its `orderBy()`
- a migration to drop the `pages.customer_reviewable` column
