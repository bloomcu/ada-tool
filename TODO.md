# TODO

Legend: `[ ]` todo · `[~]` in progress · `[x]` done · **P0** must-have ·
**P1** should-have · **P2** nice-to-have

> **Plans:** design docs for the scan-issues export / reporting work live in
> [`docs/Plan.md`](docs/Plan.md) and [`docs/Plan-reports.md`](docs/Plan-reports.md).

---

## CURRENT: Secured MCP server exposing accessibility scan data  (in progress)

Build a Laravel MCP server so AI clients can read (and later trigger) accessibility
scans. `laravel/mcp` v0.8.2 is already installed (transitively, via `laravel/boost`);
there is **no** `routes/ai.php` and **no** `app/Mcp/` yet — this is greenfield.
Generators exist: `make:mcp-server`, `make:mcp-tool`, `make:mcp-resource`,
`mcp:inspector`, `mcp:start`.

**Auth decision:** use **Sanctum** (`->middleware('auth:sanctum')`). The app already
issues tokens on login; no Passport/OAuth unless we later need a client that only
speaks OAuth 2.1. Do **not** gate this work on a Laravel 13 upgrade — v0.8.2 has the
full auth API on Laravel 12.

**⚠️ The core risk is multi-tenancy.** HTTP routes get org isolation for free via
`{organization:slug}` + `scopeBindings()`. MCP routes are flat (`/mcp/...`) with no
route binding, so **every tool must re-enforce org scoping manually** from
`$request->user()`. A miss here is a cross-tenant data leak.

### Phase 1 — Read-only server  (P0)
- [ ] Branch off `master` (prod baseline).
- [ ] Scaffold `routes/ai.php` + `make:mcp-server`; register with `auth:sanctum`.
- [ ] Read-only tools, each org-scoped from the authed user:
  - [ ] `list_sites` — sites for the org
  - [ ] `list_scans` — scan history + status for a site
  - [ ] `get_scan_summary` — violation/result counts for a scan
  - [ ] `list_pages` — pages in a scan, ranked by issue count
  - [ ] `get_page_issues` — WCAG/ADA violations on a page (enables AI remediation advice)
  - [ ] `get_scan_status` — poll a running scan
- [ ] **Tests first-class (the make-or-break):** `actingAs($user)` happy paths **plus**
      cross-tenant denial — a token from Org A must NOT read Org B's site/scan/page.
- [ ] Verify locally: `sail artisan mcp:inspector` with a real bearer token + unit tests.
      No prod release required to test.
- [ ] Ship read-only.

### Phase 2 — Action tools  (P1, guarded)
Apify runs are **billable** — treat these as costly side effects.
- [ ] `start_site_scan` — kicks off an Apify run
- [ ] `rescan_page` — single-page rescan
- [ ] `abort_scan` — intersects the open legacy-route triage below (`AbortRunController`)
- [ ] Cost guards: explicit confirmation and/or Sanctum token abilities/scopes so a
      model can't rack up Apify charges.

---

## Backlog

### Harden scan import for autonomous / unattended runs (deferred)
Scan *starting* is already automated and running each quarter without issue
(`scans:run-scheduled`, hourly, `onOneServer`+`withoutOverlapping`). But **import is still a
synchronous, frontend-triggered web request** — `ScanImportController@import` runs in
PHP-FPM, kicked off by the UI polling scan status. No queued job auto-imports a completed
scan. Fine while imports are interactive; the risk shows up only when the autonomous cadence
scales (no human/frontend present, and concurrent imports multiply the per-import memory —
the same OOM class the backfill hit). A single import already chunks the dataset (20/batch),
so the exposure is **concurrency**, not one import.
- [ ] Move `import()` into a queued Job (`ShouldQueue`); `QUEUE_CONNECTION` is already `database`.
- [ ] Auto-trigger: a scheduled poller dispatches the import job when an Apify run reports
  complete, replacing the frontend trigger.
- [ ] Bound concurrency: a dedicated import queue with a small worker count (or
  `WithoutOverlapping` per scan / a throttled `Bus::batch`) so a quarter's sites drain at a
  controlled rate instead of all at once.

### Open bugs (found while writing the test suite)
- [ ] **Scan POST response omits `status`.** `SiteScanController@store` returns the
  unrefreshed model, so the DB default (`'READY'`) isn't in the JSON (the row has it).
  Fix with `$scan->refresh()` before returning, if the client needs status immediately.
  (Confirmed still open 2026-07-15.)
- [ ] **`ScanImportController@import` unguarded `rule_results` decode.** `importPage`
  was fixed (`9e8320f`, `?? []`), but the bulk `import()` still does
  `foreach($results['rule_results'] ...)` with no guard
  ([~line 49](app/Http/Scans/ScanImportController.php#L49)). Low priority (our dataset
  always includes them) but worth a `?? []` for defense.

### Design follow-up: page rescan "empty result" limbo
The `9e8320f` guard stopped the 500 but not the underlying state problem — a rescan that
finishes with **zero importable results** leaves the page stuck "pending" forever.

**Why:** a page's rescan state lives entirely in `page.rescan_id`, and the **only** place
it clears is `importPage`'s success path. An empty dataset now returns 422 *before* that
clear, so `rescan_id` stays set and every retry 422s again. Root gap: `importPage` never
checks the Apify **run status**, so it can't distinguish `RUNNING` (dataset not ready →
422 "retry" is correct) from `SUCCEEDED` with 0 items (permanently empty → 422 forever →
limbo). This was the 2026-07-07 case.

Options (pick later):
- [ ] **Status-aware import** — on empty dataset, branch on run status: `RUNNING` → keep
  422; `SUCCEEDED`/`FAILED` → terminal: clear `rescan_id`, restore page, signal "no results."
- [ ] **Rescan status field** (`PENDING → DONE → NO_RESULTS/FAILED`) so the UI shows "no
  changes found" instead of a perpetual spinner. Cleanest UX; schema + frontend change.
- [ ] **Cancel path** — let the user abort a stuck rescan (clears `rescan_id`); ties into
  the parked abort route + Evaluation→Scan cleanup.
- [ ] **Response-contract wrinkle** — `importPage` returns plain strings (`'success'`/
  `'test'`) *and* a JSON 422. If the frontend only branches on `'success'`, it may read
  the 422 as "keep polling" and reinforce the limbo. Normalize the response shape.

### Legacy route triage (half-finished `Evaluation` → `Scan` migration)
A crawl was originally modeled as `Evaluation`, then refactored to `Scan` (same columns +
`organization_id`). **Nothing creates `Evaluation` records anymore** — the model and its
relations (`Site->evaluations()`, `Page->evaluation()`) are orphaned. `StatusController`
was migrated; `AbortRun`/`DataSet` were not, so their `Scan` routes point at missing/
mismatched methods and would 500. **Deciding input: grep the frontend for `/abort` and
`/dataset` calls before acting.**
- [ ] **DataSet** (`DataSetController`, `getDataset($dataset_id)` raw passthrough) —
  likely **redundant** (import already persists dataset → `Page` rows; UI reads pages).
  Delete the route unless a live raw-preview UI exists.
- [ ] **Abort** (`AbortRunController`, `abortRun($run_id)`) — **unique capability, currently
  unreachable** (Scan route 500s). Keep only if the UI has a cancel button — then it's a
  ~2-line Scan-based method mirroring `StatusController@show`; else delete. Ties into MCP
  Phase 2 `abort_scan`.
- [ ] **Cleanup** — the legacy Evaluation-based routes + the `Evaluation` model are dead
  and removable once the above are decided.
- [ ] **`GET /scans` → `ScanController@store`** — no `store()` method exists (only
  index/show). Dead route; fix or delete.

### Remaining test coverage
Product-domain models/services under `app/Domain/**` and `app/App/Services/**`.
- [ ] **Services (P0 seams — most fragile):** `Apify/ApifyInterface::runActor` (3pi
  include/exclude/ignoreKnown flag logic), `Apify/ApifyADAScanner` (request/response
  shaping, HTTP faked), `Scans/ScanScheduleService`, `Url/UrlService`.
- [ ] **Models (P1):** `Scan`, `Site` (3pi attributes), `Page`, `Organization` (extend),
  `User` (Sanctum tokens / org membership). (`Evaluation` — skip; orphaned, see triage.)
- [ ] **Supporting (P2):** `ScanResource`/`SiteResource` JSON shape, `SiteStore/UpdateRequest`
  rules, Policies matrix, `ScheduledScanStarted` mail, Events/Pusher payloads.
- [ ] **Organizations (`app/Http/Base/Organizations/*`):** CRUD + slug resolution / auth boundary.
- [ ] Abort/DataSet endpoint tests — **blocked** on the route triage above.

> `CDN/*` is dead code (binding commented out; nothing hits a CDN). Not worth testing;
> deletion candidate.

### Cashier / scaffold cleanup (deferred)
- [ ] **`laravel/cashier` — LIVE on the auth path, not a clean delete.** Coupled to
  `Organization` via `Billable`, `Cashier::useCustomerModel()` in `AppServiceProvider`, and
  `OrganizationResource` calls `$this->subscription('default')->ends_at` — and that resource
  **is returned by login/register/me**. To remove (only if billing is confirmed dead): strip
  `Billable`/`Subscription` from `Organization`, remove the `ends_at` line from
  `OrganizationResource`, drop `useCustomerModel()`, delete the Subscriptions controllers/
  routes, then `composer remove laravel/cashier`. **Decision needed before touching.**
- [ ] Optional base-kit scaffold removal (Files, Invitations, Teams, Categories, Tags, base
  Statuses/Comments) — no product use; lower value.

---

## History (done)

### Laravel 9 → 12 upgrade — COMPLETE, merged to `master` (prod)
Full chain done on `upgrade/laravel`, merged staging → master (PR #1).
- 9→10: framework 10.50, sanctum 3, cashier 15, phpunit 10. Backfill migration for
  `personal_access_tokens.expires_at` (Sanctum 2→3).
- 10→11: framework 11.54, sanctum 4, symfony 7, carbon 3. Kept L10 skeleton. Removed
  laravel-ignition. Published the Sanctum `create_personal_access_tokens_table` migration.
- 11→12: framework 12.63, phpunit 11. No migrations/publish/config/code changes.
- Suite green (61) after each bump. **Deployment migrations** (`add_expires_at...`, published
  `create_personal_access_tokens_table`) shipped with the master merge.

### Test suite baseline — established (P0 complete)
Went from 1 real test to 61 green. Covered: scans (list/show/trigger/rescan/status/import),
sites & pages CRUD (org-scoped), the full auth layer (login/logout/me/register/forgot/reset),
write-protection (logged-out mutations rejected; cross-org **read** intentionally open —
public launch-dashboard style), and `scans:run-scheduled`. Factories added for Organization,
User, Site, Scan, Page, Evaluation. Apify faked via `Http::fake()`; testing DB in `phpunit.xml`.
- Fixed: `SiteScanController@store` double-create (`d928e76`); `importPage` empty-dataset 500
  + unguarded `rule_results` decode (`9e8320f`).
- Pre-upgrade cleanup: removed cloudinary, `DigitalOceanCDNService`/`CDNInterface`,
  `spatie/laravel-medialibrary` + Media scaffold. (No CI gate — solo dev runs `sail artisan
  test` locally; revisit if the team grows.)

## Notes
- External services must be faked in tests — never hit live Apify / Pusher / Mailgun.
- See `AGENTS.md` for conventions once populated.
