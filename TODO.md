# TODO

## Proposed Test Suite

The app currently has **1 real test** (`tests/Unit/Domain/Organizations/OrganizationTest.php`)
plus the framework scaffolding. Before attempting the Laravel 9 → 12 upgrade we need a
regression safety net, since the small existing suite can't catch upgrade breakage.

Goal of this suite: cover the **critical business flows and integration seams** first
(auth, scans, the Apify/CDN services), then fill in model/unit coverage.

**Scope note:** the real ADA product surface is **Scans / Sites / Pages / Evaluations**
(in `routes/api.php`) plus **Auth + Organizations** as the access layer. Subscriptions,
billing, files, media, invitations, teams, categories, and tags all come from the base
kit scaffold (`routes/base/api.php`) and are **not real product surface** — they are
excluded from this suite. Revisit only if/when one is actually adopted.

Legend: `[ ]` todo · `[~]` in progress · `[x]` done · **P0** must-have before upgrade ·
**P1** should-have · **P2** nice-to-have

## Bugs surfaced while writing tests
Found by writing the tests below; not yet fixed (tests document current behavior).
- [x] **`SiteScanController@store` creates the scan twice.** **Fixed** (commit `d928e76`):
  the duplicate `create()` (paste leftover from `1248df7`, 2026-03-04) is removed; kept the
  `$site->organization_id` block. Locked with `assertDatabaseCount('scans', 1)`. Apify was
  only ever called once. **Prod cleanup — DEFERRED until after the Laravel upgrade.**
  Existing twin rows (2026-03-04 → fix deploy) are functionally harmless and self-prune at
  1 year (`model:prune` runs daily for `Scan`); only cost is a doubled `scan_count` in the
  sites list. Revisit post-upgrade: accept self-prune, or one-time de-dupe via
  `GROUP BY site_id, run_id HAVING COUNT(*) > 1`.
- [ ] **Scan POST response omits `status`.** The controller returns the unrefreshed
  model, so the DB default (`'READY'`) isn't in the JSON response (the row has it).
  Fix with `$scan->refresh()` before returning, if the client needs status immediately.
- [x] **`ScanImportController@importPage` crashes on an empty rescan dataset.** ~~Seen in
  prod 2026-07-07.~~ **Fixed** (commit `9e8320f`): guards `empty($dataset[0])` → 422.
  Covered by `ScanImportControllerTest`.
- [x] **`ScanImportController@importPage` unguarded `rule_results` decode.** **Fixed** in
  the same commit with `?? []`, tested. NOTE: `import()` still has the same shape at
  [lines 42/49](app/Http/Scans/ScanImportController.php#L42) — not yet guarded (the
  bulk import's per-item `results`/`rule_results` are assumed present); low priority since
  our dataset always includes them, but worth a `?? []` for defense.

## Design follow-up: page rescan "empty result" limbo
The `9e8320f` guard stopped the 500, but it does **not** resolve the underlying state
problem — a rescan that finishes successfully with **zero importable results** leaves the
page stuck "pending" forever. Design work, not a quick fix.

**Why it's stuck:** a page's rescan state is carried entirely by `page.rescan_id`, and the
**only** place it clears is `importPage`'s success path. An empty dataset now returns 422
*before* that clear, so `rescan_id` stays set and every retry 422s again.

**Root gap:** `importPage` never checks the Apify **run status**, so it can't distinguish:
- rescan still `RUNNING` → dataset not ready → 422 "try again" is correct; retry resolves it.
- rescan `SUCCEEDED` with 0 items → dataset permanently empty (page URL now 404s, redirects
  to a PDF, or every item filtered out as PDF/image/no-`url` by `getDataset`) → 422 forever
  → **limbo**. This was the 2026-07-07 case: "successful, but no successful results."

**Current escape hatches (both poor):** (1) re-trigger a rescan — `PageScanController@store`
overwrites `rescan_id` with no guard, so a later rescan that returns results clears it;
(2) `Scan` is `Prunable` (deletes runs >1yr), after which `$page->rescan` resolves `null`
and `importPage` falls through — self-heals in ~a year, leaving a dangling `rescan_id`.

**Options to properly resolve (pick later):**
- [ ] **Status-aware import** — on empty dataset, branch on run status: `RUNNING` → keep the
  422; `SUCCEEDED`/`FAILED` → terminal: clear `rescan_id`, return page to prior state, signal
  "rescan found no results."
- [ ] **Rescan status field** (page or scan): `PENDING → DONE → NO_RESULTS/FAILED` so the UI
  shows "no changes found" instead of a perpetual spinner. Cleanest UX; schema + frontend change.
- [ ] **Cancel path** — let the user abort a stuck rescan (clears `rescan_id`); ties into the
  parked abort route + the Evaluation→Scan cleanup.
- [ ] **Response-contract wrinkle** — `importPage` returns plain strings (`'success'`/`'test'`)
  *and* a JSON 422. If the frontend only branches on `'success'`, it may read the 422 as
  "not done, keep polling" and *reinforce* the limbo. Normalize the response shape.

---

### 0. Test infrastructure  (P0)
- [x] Configure a dedicated testing DB in `phpunit.xml` — MySQL `testing` (Sail-provisioned)
- [x] Enable `RefreshDatabase` trait usage baseline in a shared `TestCase` helper — already present
- [x] Add model factories for the product/base Eloquent models — Organization, User, Site,
      Scan, Page, Evaluation (fixed resolution: they were misplaced + used the removed `factory()` helper)
- [~] Add fake/mocks for external services: Apify (done, via `Http::fake()`), Pusher, Mailgun
- [ ] CI step to run `php artisan test` on every push (gate the upgrade PRs) — **skipped for now**
      (solo dev; run `sail artisan test` locally before each upgrade step). Revisit if the team grows.
- [x] Baseline: capture current pass state on Laravel 9 — **green**, 5/5 in `OrganizationTest`
      (`sail artisan test --testsuite Unit`). The suite had never run before this.

### 1. Feature / HTTP tests — API endpoints  (P0)
Covers the real product surface in `routes/api.php` (scans/sites/pages) plus the
auth + organization access layer from `routes/base/api.php`.

**Scans (`app/Http/Scans/*`)** — core product domain, highest priority
- [x] List / show scan (`ScanController@index/@show`) — org-scoped, authed vs public
- [x] Site scan trigger (`SiteScanController@store`) — Apify faked, persists run, forwards 3pi flag, auth required
- [x] Page rescan (`PageScanController@store`) — Apify faked, single-page scan, links rescan_id, no enqueue, auth required
- [x] Scan status (`Scans/StatusController@show`) — Apify faked, persists status, org scoping
- [x] Scan import (`ScanImportController@import` / `@importPage`) — Apify dataset → Pages, tallies, empty-dataset guards
- [ ] Abort run (`AbortRunController@abortRun`) — blocked: see misconfigured routes below
- [ ] DataSet retrieval (`DataSetController`) — blocked: see misconfigured routes below

> **Misconfigured legacy routes (in `routes/api.php`, need triage before testing):**
> These reference controller methods that don't exist or bind the wrong model, so they
> would 500 on any request — not simple guard bugs, they need wiring decisions.
> - `GET /scans` → `ScanController@store` — no `store()` method exists (only index/show).
> - `GET /scans/{scan}/dataset` → `DataSetController@show` — only `dataset()` exists.
> - `GET /scans/{scan}/abort` → `AbortRunController@abortRun(…, Evaluation $evaluation)` —
>   route param is `{scan}` but the method expects `$evaluation`; name mismatch means the
>   id never binds. Same pattern on the unauthed `/scans/status/{evaluation}` group.
> Decide per route: delete if dead, or fix the method/binding. Confirm against frontend usage first.
>
> **Triage findings (root cause — a half-finished `Evaluation` → `Scan` migration):**
> The app originally modeled a crawl as `Evaluation`; it was refactored to `Scan`
> (same columns + `organization_id`). **Nothing creates `Evaluation` records anymore**
> — the model and its relations (`Site->evaluations()`, `Page->evaluation()` on a dropped
> column) are orphaned. `StatusController` was migrated (new `show(Organization, Scan)`);
> `AbortRun`/`DataSet` were not, so their Scan routes point at missing/mismatched methods.
> - **DataSet** (`getDataset($dataset_id)` raw passthrough): **mostly redundant** — the
>   real work (dataset → `Page` rows) is done by `ScanImportController@import`, and the UI
>   reads the persisted pages. Likely **delete the route** unless a live raw-preview UI exists.
> - **Abort** (`abortRun($run_id)`): **unique capability, currently unreachable** (Scan route
>   500s; only the dead Evaluation route "works"). Keep only if the UI has a cancel button —
>   then it's a 2-line Scan-based method mirroring `StatusController@show`; else delete.
> - **Cleanup**: the legacy Evaluation-based routes + the `Evaluation` model are dead and
>   removable. **Deciding input:** grep the frontend for `/abort` and `/dataset` calls.

**Sites & Pages (`app/Http/Sites/*`, `app/Http/Pages/*`)** — authed, org-scoped
- [x] Site CRUD (`SiteController`) — index/store/show/update/destroy, validation, org scoping, auth
- [x] Per-site include/exclude 3pi flags — covered in `SiteScanControllerTest` (forwarded to Apify) + store/update
- [x] Page show (`PageController@show`) — public, scope-bound org→scan→page, decoded results, 404s on mismatch

**Auth access layer (`app/Http/Base/Auth/*`)** — needed to reach authed routes; Sanctum 2→4 sensitive
- [x] Login (`AuthLoginController`) — token issued, revokes old tokens, bad creds 422, validation
- [x] Logout (`AuthLogoutController`) — revokes all tokens, requires auth
- [x] `me` (`AuthMeController`) — returns authed user, requires auth
- [x] Register (`AuthRegisterController`) — creates org+user+token (pwned faked), dup email/weak pw/missing fields
- [x] Password forgot / reset (`AuthPasswordForgot/ResetController`) — neutral forgot, reset via broker token, invalid token 422

**Organizations (`app/Http/Base/Organizations/*`)** — scans/sites are scoped by org slug
- [ ] Organization CRUD (`OrganizationController`) — index/store/show/update/destroy
- [ ] Slug resolution + auth boundary (unauthed → 401)

> **Excluded (base-kit scaffold, not product surface):** subscriptions/billing, files,
> media, invitations, teams, users, categories, tags, base statuses/comments. Don't
> write tests for these unless the product actually adopts them.

### 2. Unit tests — Models  (P1)
Product-domain models under `app/Domain/**` — relationships, casts, scopes, accessors.
- [ ] `Scan` — relationships (site/page/evaluations), status casts, scopes
- [ ] `Site` — relationships, 3pi include/exclude attributes, scopes
- [ ] `Page` — relationships to Site/Scan
- [ ] `Evaluation` — relationship to Scan, result parsing
- [ ] `Organization` — extend existing test; relationship to sites/scans
- [ ] `User` (Authenticatable + Sanctum `HasApiTokens`) — org membership, auth tokens

> Scaffold-only models (`Team`, `Invitation`, `Category`, `Comment`, `File`, `Status`,
> `Tag`, `Plan`) are out of scope per §1.

### 3. Unit tests — Services  (P0 for external seams)
These wrap third-party APIs and are the most fragile across a framework upgrade.
- [ ] `Apify/ApifyInterface::runActor` — assert 3pi include/exclude/ignoreKnown flag logic (recently changed — commit f340555)
- [ ] `Apify/ApifyADAScanner` — request shaping, response mapping (HTTP faked)
- [ ] `Scans/ScanScheduleService` — scheduling logic, edge cases
- [ ] `Url/UrlService` — URL normalization/validation edge cases

> `CDN/DigitalOceanCDNService` + `CDNInterface` are **dead code** — the binding is
> commented out in `AppServiceProvider` and no product code hits a CDN/disk (scans read
> Apify datasets directly). Not worth testing; candidate for deletion (see §6).

### 4. Unit tests — supporting classes  (P2)
- [ ] Resources: `ScanResource`, `SiteResource` — JSON shape stability (guards API contract)
- [ ] Requests: `SiteStoreRequest`, `SiteUpdateRequest` — validation rules
- [ ] Policies (`app/App/Policies/*`) — authorization matrix per role
- [ ] Mail: `ScheduledScanStarted` — renders, correct recipients
- [ ] Events (`app/App/Events/*`) + Pusher broadcast payloads
- [ ] Helpers, Traits, Scopes (`app/App/*`)

### 5. Console / scheduled tasks  (P1)
- [ ] Scheduled scan command(s) — dispatches `ScheduledScanStarted`, respects schedule
- [ ] Any `routes/console.php` commands

---

## Pre-upgrade cleanup — remove dead / scaffold dependencies  (do FIRST)

Verified against the codebase (2026-07-03). Removing these shrinks the Laravel 9→12
surface area — three of them were the worst breaking-change offenders in the upgrade.
Do this **before** writing the upgrade PRs; fewer packages = fewer things to break.

- [ ] **`cloudinary-labs/cloudinary-laravel` — DEAD, drop outright.**
  Only referenced in `composer.json`. No code, no config, no published `config/cloudinary.php`.
  → `composer remove cloudinary-labs/cloudinary-laravel`. Zero code changes.

- [ ] **`DigitalOceanCDNService` / `CDNInterface` — DEAD internal code, delete.**
  Binding commented out in `AppServiceProvider:38`; nothing resolves it. Only live
  trace is `config('cdn.cdn_url')` inside the scaffold `File` model accessor.
  → Delete `app/App/Services/CDN/`, `config/cdn.php` (goes with the Files scaffold below).

- [ ] **`spatie/laravel-medialibrary` — scaffold-only, removable (light surgery).**
  Used by `Media` model (extends Spatie `BaseMedia`) + `MediaController@addMedia` +
  `config/media-library.php`. Already commented out on the `Organization` model. No
  scan/site/page code touches it.
  → Delete `app/Domain/Base/Media/`, `app/Http/Base/Media/`, media routes, config;
  then `composer remove spatie/laravel-medialibrary`.

- [ ] **`laravel/cashier` — scaffold routes, but LIVE on the auth path (moderate surgery).**
  Coupled to `Organization` via the `Billable` trait, `Cashier::useCustomerModel()` in
  `AppServiceProvider`, and — critically — `OrganizationResource` calls
  `$this->subscription('default')->ends_at`, and that resource **is returned by
  login/register/me**. Not a clean delete.
  → If billing is truly out of scope: strip `Billable`/`Subscription` from `Organization`,
  remove the `ends_at` line from `OrganizationResource`, drop `useCustomerModel()`, delete
  the Subscriptions controllers/routes, then `composer remove laravel/cashier`.
  → **Decision needed:** confirm billing is dead before touching this (it runs on login).

- [ ] Optional companion scaffold to remove alongside the above (all base-kit, no product
  use): Files, Invitations, Teams, Categories, Tags, base Statuses/Comments controllers +
  models + routes. Reduces upgrade surface further but is lower-value; scope as desired.

## Coverage targets
- **Before upgrade (P0 complete):** all critical paths (auth, scans, Apify service) green on Laravel 9.
- **During upgrade:** run full suite after each major bump (9→10→11→12); no P0 regressions.
- **Stretch:** ~60%+ line coverage on `app/Domain` (scans/sites/pages/evaluations) and `app/App/Services`.

## Notes
- External services must be faked in tests — never hit live Apify / Pusher / Mailgun.
- Add factories first; most feature tests are blocked on them.
- See `AGENTS.md` for conventions once populated.
