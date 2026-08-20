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
- **[ ] A real MULTI-PAGE scan export** — needed to validate cross-page recurrence; dev has
  only single-page QA scans. Run `scans:export-issues <id>` on **prod** against a multi-page
  site and paste the JSON.
- **[ ] 1–2 example launch reports** — behind the login-protected Vue frontend, so paste as
  text or print-to-PDF. Redact freely; only structure/tone is needed.

## Build tasks

- **[ ] P0** `PageIssueFormatter` extracted from `GetPageIssuesTool` (+ tests).
- **[ ] P0** `ScanIssuesExport` aggregate over `Page::where('scan_id', …)` (+ tests).
- **[ ] P0** Recurrence analyzer: for each `(rule_id, element_identifier)`, count
  **distinct pages it appears on** (presence-per-page, NOT raw occurrences — identifiers are
  non-unique within a page) → `global | cms_editable` label + pages-affected count (+ tests).
- **[ ] P0** Rule map seeded from the trial scan's distinct `rule_id`s (doc_url + tiebreaker).
- **[ ] P0** `scans:export-issues {scan}` command → scorecard JSON.
- **[ ] P1** Report generator (example reports + scorecard → prose).
- **[ ] P1** Refactor `GetPageIssuesTool` onto the shared core.

## Open questions

- "Prior art" flavor for v1: WCAG guidance grounding, own proven patterns, or self-diff over
  time? Near-term default: **recurrence + a small WCAG-guidance rule map**; the example
  reports drive tone. Proven-patterns/self-diff deferred.
- Report consumer for v1: **Claude writing prose** (launch-style report). A structured
  scorecard for the builder to ingest comes later, off the same `ScanIssuesExport`.
