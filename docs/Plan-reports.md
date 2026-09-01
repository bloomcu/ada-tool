# Plan — Client-facing accessibility reports

Legend: `[ ]` todo · `[~]` in progress · `[x]` done · **P0** must-have ·
**P1** should-have · **P2** nice-to-have

Parent: [Plan.md](Plan.md) — overall "accessibility data as a service" plan and the
shared core this feature builds on.

---

## Goal

From a scan, produce a client-facing remediation **report** that reads like the reports
clients already receive at launch — but split so a client can see **what they can fix
themselves in the CMS** vs. **what is global/template-level** (our job).

---

## 📌 PINNED — next-session starting point (validated 2026-08-20 on northwoods, 262 pages)

**Do next session:** (1) review the rule map in depth (Bryan owns the `tier` calls), then
(2) implement `ScanIssueClassifier` + fold into the export/report. Model is validated; the
work is curating the map and building it.

**Classification model = three axes.** Two are a curated per-rule map, one is computed:
1. **`cause`** (curated): `authoring` (content editor) · `structural` (dev/template markup+ARIA) · `design` (theme/CSS).
2. **`tier`** (curated — *we* own this, the scanner can't be trusted): `ada_required` ·
   `best_practice` · `suppress`. **The scanner marks EVERY rule `required`**, so its
   `required` flag is useless. `TITLE_2` (@h1@ must match @title@) is **bogus / suppress** —
   a mislabeled best-practice with no real ADA spec (Bryan's call).
3. **`recurrence`** (computed from data): `systematic` (site-wide → ONE template/config fix,
   threshold ~≥20% of pages) vs `per_page` (isolated → editor action). This auto-corrects
   borderline `cause` calls — e.g. `TITLE_2` on 74 pages self-flags as systematic.
   Count **presence-per-page**, not raw occurrences (identifiers are non-unique per page).
- **+ Third-party detection** via element `id`/`class` **signature map**: `SvgjsSvg*` (SVG.js),
  `gm-*` (Google Maps) = third-party (escalate/remove, not the client's fault); bloomcu BEM
  (`hero__`, `mega-nav__`, `btn--`) = first-party. `id`/`class` present on ~35%/27% of elements.

**Why this matters (northwoods numbers):** raw "283 violations" is 91% noise — **147** were
bogus `TITLE_2`, **111** were the third-party SVG trio (`IMAGE_8`/`LANDMARK_2`/`WIDGET_3` on
one `SvgjsSvg` element). Real client-actionable ≈ **25 structural violations + 50 authoring
warnings**. The report headline must be **segmented by who acts**, not a raw count:
customer action list (**24 per-page /blog/ authoring pages**) · systematic fixes · dev/theme · third-party.

**Corrections from Bryan's spot-check (must fold into the model):**
- **Severity is a required filter.** The UI's "errors" = **violations (`V`) only**; warnings
  (`W`) are advisory. My draft action list mixed them, so warning-only pages (Member
  Appreciation Days 2024, 'Tis the Season — both viol=0/warn=3) wrongly showed as errors.
  In this scanner the heading/link **authoring** rules are mostly `W`; `LINK_1`/`TITLE_2`
  are `V`. → Customer *error* list = violations-first; warnings = separate advisory tier.
- **Dedupe pages by URL.** 1 duplicate URL found in 121 pages (`/about/news/`, 2 page rows) —
  likely a one-off rescan leaving a second row. Minor now, but dedupe (keep latest) and
  investigate whether single-page rescans create duplicate rows.
- **Export vs UI — RESOLVED (was staleness, not a counting bug).** A second export minutes
  later matched the live UI on all spot-checked pages: 8 pages had been rescanned/fixed
  between exports and dropped their issues (Member Appreciation & 'Tis the Season → clean;
  Valleyfair V4→V2 as its `TITLE_2` was fixed). Confirms most-recent-per-page works
  end-to-end — the export reflects live per-page rescan state. **Implication:** a report is a
  point-in-time snapshot; note the scan/export timestamp on it.
- **`TITLE_2` is NOT scanner-suppressed** — it's ~145 live violations site-wide and is
  technically fixable (fix the h1/title). Its `tier` (suppress vs best_practice) is purely
  Bryan's policy call, but it's the single biggest lever on the headline count.

**Artifacts:** sample export at `storage/app/reports/ada-report.json` (gitignored). Ad-hoc
analysis scripts were in the session scratchpad (ephemeral — rebuild as real code next time).

## Near-term scope (the launch-project trial)

1. **[ ] P0** Classify **CMS-editable** issues vs **global/template** issues.
2. **[ ] P0** Highlight **patterns** in the CMS-editable issues (e.g. "headings used wrong
   on 14 pages") and **link to documentation** for each.
3. **[ ] P0** Use **example reports** clients were sent at launch as prior art; have Claude
   generate a similar report.
4. **[ ] P1** Trial on a **launch project** in the next few days.
5. **[ ] P2** Wire into the **quarterly email** if it works.

## How classification actually works

### Primary signal — cross-page recurrence (mostly free)

Each issue element carries an `element_identifier` (+ usually-empty id/class). Across a
scan:

- **Same `(rule_id, element_identifier)` on ~every page → global/template.** Header, nav,
  footer, skip-link, design-token contrast — baked into the theme, a dev fixes once.
- **Same rule, different identifiers scattered per page → CMS-editable content.** Heading
  order in the body, image alt, ambiguous link text — the editor fixes each instance.

This single group-by delivers **both** scope #1 (classify) **and** scope #2 (patterns).
It is the centerpiece — preferred over a hand-maintained "this rule is always global" list.

> **`element_identifier` format — CONFIRMED (2026-08-20, scan 22).** It is
> **`"<tag>: <accessible name / text>"`** (e.g. `"a: Login"`, `"p: This is an error…"`),
> **not** a DOM path or CSS selector — it is content/text-derived. Consequences for the
> classifier:
> - ✅ Template elements with stable text (nav/footer links) yield the *same* identifier on
>   every page → recurrence detection is viable.
> - ⚠️ **Not unique within a page** — two distinct DOM nodes with the same tag+text collapse
>   to one identifier (observed: two `"h2: This is an h2"`). So recurrence must count
>   **presence-per-page** (does this identifier appear on page X?), **never** raw occurrence
>   counts.
> - ⚠️ **id/class are usually empty** — don't rely on them for element identity.
> - ⚠️ **Text-sensitive** — page-specific template text won't match across pages; recurrence
>   catches *identical* text only. Rule type is the tiebreaker (structural rules lean
>   global/dev; content rules lean CMS-editable).
>
> **Still unvalidated:** cross-page recurrence itself. The dev DB has **only single-page
> scans** (QA fixtures), so clustering across pages can't be tested here — it needs a real
> **multi-page** scan export from prod (see Data needs).

### Secondary signal — a data-first rule map

Recurrence gives *global vs local*; it does **not** give doc URLs or resolve genuinely
ambiguous rules. So maintain a small lookup:

```
rule_id → { category, doc_url, editable_hint }
```

- **Build it against the rules that actually appear in the trial scan** (~20–40), not all
  of WCAG. Every entry is one we'll actually use.
- Recurrence is the primary classifier; this map is the **doc-link + tiebreaker** layer.

## Report generation

- **Example reports are the spec, not just few-shot.** Their section order/voice define the
  output schema. Look at 1–2 real ones before freezing the scorecard JSON — whatever fields
  the report wants, add to `ScanIssuesExport` then.
- Generation input: `example_reports (style/tone) + scorecard JSON (facts)` → matching report.

## Test strategy

The trial has a blind spot worth calling out:

- **Launch scan = good happy-path / report-prose test.** It is "almost entirely CMS
  formatting errors, basically no global issues" — representative of what the report needs
  to *say*, so it validates tone/usefulness well.
- **Launch scan = useless for classifier discrimination.** A classifier that stamps
  *everything* "CMS-editable" scores 100% here while the global path is completely untested.
  A false negative on "global" is invisible.
- **"No global issues" is itself unverified** — indistinguishable between "the bloomcu
  theme is genuinely clean" (a great report narrative) and "the detector is broken."

**Therefore:**

- **[ ] P0** Keep the launch scan as the **happy-path / prose** test.
- **[ ] P0** Add **one messy, non-bloomcu / third-party scan** as a **negative control** to
  confirm the global detector *fires* when it should. This doubles as the first test of the
  long-term "existing websites" use case.
- Trust the classifier only when global **fires** on the rough site **and stays empty** on
  the clean launch site.

## Validation happens on prod

Prod DB is unreachable from dev (see [Plan.md](Plan.md) constraints). So:

- Build the core + `scans:export-issues` command against the known shape with **fixture
  tests** on dev.
- **Deploy the command; run it on prod** against the launch scan (and a rough site) to get
  real scorecard JSON. Paste output back to tune the classifier. No data leaves the
  environment except what is deliberately pasted.

## Data needs (only these require a human hand-off)

- **[x] One real page's `results` blob** — DONE (scan 22, 2026-08-20). Confirmed
  `element_identifier` = `"<tag>: <text>"` (see the format callout above).
- **[x] A real MULTI-PAGE scan export** — DONE (northwoods scan 1909, 262 pages,
  `storage/app/reports/ada-report-2.json`). Recurrence validated: clear global cluster vs
  CMS-editable tail (see PINNED block).
- **[x] 1–2 example launch reports** — DONE: `storage/app/examples/{acadia,central,siu}.md`
  (define the customer-report format/tone).

## Build tasks

- **[x] P0** `PageIssueFormatter` — shipped (uncapped default; opt-in cap for MCP).
- **[x] P0** `ScanIssuesExport` aggregate over `Page::where('scan_id', …)` — shipped.
- **[x] P0** `scans:export-issues {scan}` command — shipped (+ dashboard download endpoint).
- **[ ] P0** Recurrence analyzer: for each `(rule_id, element_identifier)`, count **distinct
  pages** (presence-per-page, NOT raw occurrences) → `global | cms_editable` + pages affected.
  *(Prototyped in scratchpad scripts; needs to become real code.)*
- **[ ] P0** Rule map: `rule_id → {cause, tier, owner, doc_url}` + the severity (V/W) filter.
  Seed = Bryan's annotations in `storage/app/reports/northwoods-grouped-annotated.txt`.
- **[ ] P1** Report generator (example reports + classified export → prose). *(Done once by
  hand for northwoods — `northwoods-report.md` + `-elisha-punchlist.md`; not automated.)*
- **[ ] P1** Refactor `GetPageIssuesTool` onto the shared core (deferred; stashed on `mcp`).

## Open questions

- "Prior art" flavor for v1: WCAG guidance grounding, own proven patterns, or self-diff over
  time? Near-term default: **recurrence + a small WCAG-guidance rule map**; the example
  reports drive tone. Proven-patterns/self-diff deferred.
- Report consumer for v1: **Claude writing prose** (launch-style report). A structured
  scorecard for the builder to ingest comes later, off the same `ScanIssuesExport`.
