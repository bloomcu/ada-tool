<?php

namespace DDD\Domain\Pages;

/**
 * TEMPORARY / disposable first-pass heuristic for the "review this first" UI flag.
 *
 * A flat allow-list of rule ids we currently treat as "probably customer (CMS)
 * editable" — headings, link text, alt text, tables. Deliberately coarse: it errs
 * toward flagging (a nudge to review), because under-flagging a real editor issue is
 * worse than over-flagging. It has NO notion of recurrence, third-party ownership, or
 * false-positives — those come with the real classifier, which will replace this.
 *
 * Delete this class (and the `pages.customer_reviewable` column) when the classifier lands.
 */
class CustomerEditableRules
{
    /** @var list<string> */
    private const RULES = [
        'HEADING_1', 'HEADING_2', 'HEADING_3', 'HEADING_4', 'HEADING_5', 'HEADING_6',
        'LINK_1', 'LINK_2',
        'IMAGE_1', 'IMAGE_2',
        'TABLE_1', 'TABLE_5',
        // Excluded on purpose:
        //  CONTROL_4  ("buttons need visible text") — in real scans it's the site-wide notice
        //    banner / Google Maps controls (template/third-party), often on EVERY page → would
        //    flag the whole site and make the badge useless.
        //  CONTROL_10 ("labels must be unique") — form/plugin territory, not reliably editor content.
    ];

    public static function has(?string $ruleId): bool
    {
        return $ruleId !== null && in_array($ruleId, self::RULES, true);
    }

    /**
     * True if a decoded `results` payload contains any FAILING (violation/warning)
     * rule that is on the customer-editable allow-list.
     *
     * @param  array<string, mixed>  $results  Decoded `page.results`.
     */
    public static function reviewable(array $results): bool
    {
        foreach ($results['rule_results'] ?? [] as $rule) {
            if (! is_array($rule)) {
                continue;
            }
            $failing = ($rule['elements_violation'] ?? 0) > 0
                || ($rule['elements_warning'] ?? 0) > 0;
            if ($failing && self::has($rule['rule_id'] ?? null)) {
                return true;
            }
        }

        return false;
    }
}
