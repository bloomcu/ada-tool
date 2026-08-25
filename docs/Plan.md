# Plan — Accessibility data as a consumable service

Legend: `[ ]` todo · `[~]` in progress · `[x]` done · **P0** must-have ·
**P1** should-have · **P2** nice-to-have

Related plans:
- [Plan-reports.md](Plan-reports.md) — the near-term deliverable: client-facing
  accessibility reports generated from a scan.

---

## Vision

Turn the stored ADA scan results into something **consumable** — by humans (client
reports) and by machines (an LLM-powered site builder that wants a page's issues in
real time). Today the results sit in a ~117 KB-per-page JSON blob (`pages.results`)
that nothing outside the app can easily use.

- **Short term:** generate a client-facing remediation **report** from a scan
  (see [Plan-reports.md](Plan-reports.md)). Forcing function that proves the data shape.
- **Long term:** expose **real-time page results** to the site-builder platform so an
  LLM can read accessibility findings for a page it is building/editing.

## Core principle: the transport is cheap, the shaping is the asset

MCP tool, REST endpoint, and console command all need the **same** thing underneath:
the filter that turns the raw blob into a compact, LLM-parsible issues structure
(violations/warnings only, offending elements only, metadata dropped, capped per rule).

That filter — **not** any one transport — is what we build and test. Everything else is
a thin (~20-line) adapter over it.

### The shared core (transport-agnostic)

- **`PageIssueFormatter`** — one page's `results` blob → trimmed issues (extract the
  logic currently private inside `app/Mcp/Tools/GetPageIssuesTool.php`).
- **`ScanIssuesExport`** — aggregate the formatter over every page in a scan.
- **Recurrence analyzer** — group `(rule_id, element_identifier)` across a scan's pages
  (drives global-vs-CMS-editable classification; see Plan-reports.md).

Then refactor `GetPageIssuesTool` to call the shared core so MCP stays in sync.

## Delivery surfaces — pick per use case, not one-size-fits-all

| Surface | When it fits | Notes |
| --- | --- | --- |
| **Console command** | Runs **where the data is (prod)**; emits a JSON blob to feed Claude | **First deliverable** — only path that works while prod DB is unreachable from dev |
| **REST endpoint** (Sanctum) | Deterministic service-to-service pull (site builder already knows the page/URL) | Simpler than MCP for fixed queries: cacheable, rate-limitable, versionable, uses existing identity provider |
| **MCP tool** | *Agentic* Claude-in-the-loop exploration (list sites → scans → drill into worst page) | Already built for read access; keep it, point it at the shared core |

**On MCP vs REST:** the differentiator is *who decides which call to make*, not auth
(the MCP server is already `auth:sanctum`, same as a REST route would be). Agentic
exploration → MCP. Fixed "give me issues for this page" pulls → REST. They coexist over
the same core.

## Constraints & landmines (design around these)

- **Prod DB is not reachable from dev.** Real scans (launch/client/rough sites) live in
  prod only. Consequence: discovery/validation runs **on prod via the console command**,
  not by querying from here. Build the core against the known blob shape with fixtures.
- **`element_identifier` calibration.** The global-vs-content recurrence signal rides on
  `element_identifier` being stable/comparable across pages. Validate with **one real
  page's `results`** (browser Network tab, or a prod `tinker`).
- **Single-active-token landmine** (see CLAUDE.md / TODO.md). Login/logout do a blanket
  `tokens()->delete()`. A shared service-identity token for the site builder would be
  nuked the next time any human logs into the web app. **Fix before the REST/service
  path:** name the service token and scope the deletes.
- **"Real-time" has two meanings.** Latest *stored* results = a cheap read (what we have).
  A *fresh* crawl = a billable, async Apify run. Keep them separate in any API:
  `GET issues` (cheap) vs. `POST rescan` (async job). Don't let a GET imply fresh data.
- **Most-recent-per-page is free.** A completed rescan overwrites `pages.results` in place
  and clears `rescan_id` (`ScanImportController::importPage`). So "latest results per page,
  by scan" is just `Page::where('scan_id', $id)` reading `results` — no versioning needed.

## Data shape reference (from existing code)

`pages.results` (JSON, decoded by the `Page` model):

```
{
  "eval_url": "https://…",
  "rule_results": [
    {
      "rule_id": "…",
      "rule_summary": "…",
      "rule_required": true,
      "elements_violation": 5,
      "elements_warning": 0,
      "element_results": [
        { "result_value_nls": "V", "element_identifier": "…", "id": "…", "class": "…" }
      ]
    }
  ]
}
```

- Failing severities: `V` → violation, `W` → warning. (`P` pass, `MC` manual-check dropped.)
- `pages` columns: `scan_id`, `results`, `violation_count`, `warning_count`, `title`,
  `rescan_id`.
- Per-rule element cap in the existing tool: `MAX_ELEMENTS_PER_RULE = 25`.

## Sequencing

1. **[~] P0** Shared core: `PageIssueFormatter` **[x] shipped** (uncapped default),
   `ScanIssuesExport` **[x] shipped**; recurrence analyzer **[ ]** (validated by hand, not yet
   code); `GetPageIssuesTool` refactor **[ ]** (deferred — MCP tool stashed on `mcp`).
2. **[x] P0** `scans:export-issues {scan}` console command — shipped to master.
3. **[~] P0** Reports feature — model validated end-to-end, one report generated by hand;
   classifier not yet code. See [Plan-reports.md](Plan-reports.md).
4. **[ ] P1** Live REST/JSON for the site-builder pull — distinct from the shipped dashboard
   **download** endpoint (`GET …/scans/{scan}/issues/export`, auth:sanctum). Fix the
   token-naming landmine first.
5. **[ ] P2** Wire the report into a scheduled **quarterly** email (`->quarterly()`). Scheduler
   blocker **cleared** — Forge job set to Every Minute (fixed 2026-08-25).

## Open questions

- Which flavor(s) of "prior art" for the report (WCAG guidance / own proven patterns /
  self-diff over time)? Near-term answer is documented in Plan-reports.md.
- REST resource shape & versioning for the site builder (defer until the report freezes
  the JSON shape).
