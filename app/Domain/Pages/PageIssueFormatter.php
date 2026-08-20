<?php

namespace DDD\Domain\Pages;

use Illuminate\Support\Collection;

/**
 * Reduces a page's raw `results` payload to the actionable subset: only the rules
 * that reported violations or warnings, and within each rule only the failing
 * elements (passes/manual-checks and the `_nls`/code metadata are dropped).
 *
 * A stored `results` blob is huge (~117 KB observed) because `element_results`
 * lists every evaluated element, mostly passes. This trimming is what makes the
 * data cheap to consume. It is transport-agnostic on purpose — the
 * `scans:export-issues` command composes it, and a REST endpoint could too
 * (see docs/Plan.md).
 *
 * By default NO per-rule element cap is applied: the export/report path needs the
 * complete set of failing elements so its recurrence analysis can tell a
 * template-wide (global) issue from a per-page (CMS-editable) one. A cap is opt-in
 * via the constructor for context-budgeted callers (e.g. an LLM tool).
 */
class PageIssueFormatter
{
    /**
     * `result_value_nls` codes that represent an actionable failure, mapped to a
     * human-readable severity. Passes ("P"), manual checks ("MC") and N/A are omitted.
     */
    private const FAILING_SEVERITIES = [
        'V' => 'violation',
        'W' => 'warning',
    ];

    /**
     * @param  int|null  $maxElementsPerRule  Cap on offending elements returned per rule.
     *                                        Null (default) returns every failing element —
     *                                        required for the export/report path. Pass a
     *                                        positive int only for context-budgeted callers.
     */
    public function __construct(private ?int $maxElementsPerRule = null)
    {
    }

    /**
     * Trim a decoded `page.results` payload down to its failing rules and elements.
     *
     * @param  array<string, mixed>  $results  The decoded `page.results` payload.
     * @return array{issue_count: int, issues: array<int, array<string, mixed>>}
     */
    public function format(array $results): array
    {
        $issues = (new Collection($results['rule_results'] ?? []))
            ->filter(fn ($rule): bool => is_array($rule)
                && (($rule['elements_violation'] ?? 0) > 0
                    || ($rule['elements_warning'] ?? 0) > 0))
            ->map(fn (array $rule): array => $this->formatRule($rule))
            ->sortByDesc('elements_violation')
            ->values()
            ->all();

        return [
            'issue_count' => count($issues),
            'issues' => $issues,
        ];
    }

    /**
     * Shape a single failing rule for output, including its offending elements.
     *
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    private function formatRule(array $rule): array
    {
        $failing = (new Collection($rule['element_results'] ?? []))
            ->filter(fn ($element): bool => is_array($element) && isset(
                self::FAILING_SEVERITIES[$element['result_value_nls'] ?? null]
            ))
            ->values();

        $kept = $this->maxElementsPerRule === null
            ? $failing
            : $failing->take($this->maxElementsPerRule);

        $elements = $kept
            ->map(fn (array $element): array => [
                'identifier' => $element['element_identifier'] ?? null,
                'id' => $element['id'] ?? null,
                'class' => $element['class'] ?? null,
                'severity' => self::FAILING_SEVERITIES[$element['result_value_nls']],
            ])
            ->values()
            ->all();

        return [
            'rule_id' => $rule['rule_id'] ?? null,
            'summary' => $rule['rule_summary'] ?? null,
            'required' => $rule['rule_required'] ?? null,
            'elements_violation' => $rule['elements_violation'] ?? 0,
            'elements_warning' => $rule['elements_warning'] ?? 0,
            'elements' => $elements,
            'elements_truncated' => $this->maxElementsPerRule !== null
                && $failing->count() > $this->maxElementsPerRule,
        ];
    }
}
