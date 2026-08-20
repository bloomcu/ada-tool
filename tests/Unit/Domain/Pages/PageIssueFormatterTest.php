<?php

namespace Tests\Unit\Domain\Pages;

use Tests\TestCase;
use DDD\Domain\Pages\PageIssueFormatter;

class PageIssueFormatterTest extends TestCase
{
    private function format(array $results, ?int $cap = null): array
    {
        return (new PageIssueFormatter($cap))->format($results);
    }

    /** @test */
    public function it_keeps_only_rules_with_violations_or_warnings()
    {
        $out = $this->format([
            'rule_results' => [
                // Passing rule — dropped.
                ['rule_id' => 'AUDIO_1', 'rule_summary' => 'Audio transcript', 'rule_required' => true, 'elements_violation' => 0, 'elements_warning' => 0],
                // Violating rule — kept.
                ['rule_id' => 'IMAGE_1', 'rule_summary' => 'Images need alt text', 'rule_required' => true, 'elements_violation' => 1, 'elements_warning' => 0],
                // Warning-only rule — kept.
                ['rule_id' => 'LINK_2', 'rule_summary' => 'Link text should be descriptive', 'rule_required' => false, 'elements_violation' => 0, 'elements_warning' => 1],
            ],
        ]);

        $this->assertSame(2, $out['issue_count']);
        $ruleIds = array_column($out['issues'], 'rule_id');
        $this->assertContains('IMAGE_1', $ruleIds);
        $this->assertContains('LINK_2', $ruleIds);
        $this->assertNotContains('AUDIO_1', $ruleIds);
    }

    /** @test */
    public function it_surfaces_only_failing_elements_with_severity()
    {
        $out = $this->format([
            'rule_results' => [
                [
                    'rule_id' => 'IMAGE_1',
                    'rule_summary' => 'Images need alt text',
                    'rule_required' => true,
                    'elements_violation' => 1,
                    'elements_warning' => 1,
                    'element_results' => [
                        ['element_identifier' => 'img: header-logo-no-alt', 'id' => 'logo', 'class' => '', 'result_value_nls' => 'V'],
                        ['element_identifier' => 'img: decorative-maybe', 'id' => '', 'class' => 'hero', 'result_value_nls' => 'W'],
                        ['element_identifier' => 'img: footer-has-good-alt', 'id' => '', 'class' => '', 'result_value_nls' => 'P'],
                        ['element_identifier' => 'img: needs-human-review', 'id' => '', 'class' => '', 'result_value_nls' => 'MC'],
                    ],
                ],
            ],
        ]);

        $elements = $out['issues'][0]['elements'];
        $this->assertCount(2, $elements);

        $identifiers = array_column($elements, 'identifier');
        $this->assertContains('img: header-logo-no-alt', $identifiers);
        $this->assertContains('img: decorative-maybe', $identifiers);
        // Passes and manual-checks must not leak.
        $this->assertNotContains('img: footer-has-good-alt', $identifiers);
        $this->assertNotContains('img: needs-human-review', $identifiers);

        $severities = array_column($elements, 'severity');
        $this->assertSame(['violation', 'warning'], $severities);
        $this->assertFalse($out['issues'][0]['elements_truncated']);
    }

    /** @test */
    public function it_returns_every_failing_element_uncapped_by_default()
    {
        // The export/report path must not lose elements — recurrence analysis needs them all.
        $elements = [];
        for ($i = 0; $i < 30; $i++) {
            $elements[] = ['element_identifier' => "ELEM_{$i}", 'id' => '', 'class' => '', 'result_value_nls' => 'V'];
        }

        $out = $this->format([
            'rule_results' => [
                ['rule_id' => 'IMAGE_1', 'rule_summary' => 'Images need alt text', 'rule_required' => true, 'elements_violation' => 30, 'elements_warning' => 0, 'element_results' => $elements],
            ],
        ]);

        $issue = $out['issues'][0];
        $this->assertCount(30, $issue['elements']);
        $this->assertFalse($issue['elements_truncated']);
        $this->assertContains('ELEM_29', array_column($issue['elements'], 'identifier'));
    }

    /** @test */
    public function it_caps_elements_per_rule_only_when_a_cap_is_given()
    {
        $elements = [];
        for ($i = 0; $i < 30; $i++) {
            $elements[] = ['element_identifier' => "ELEM_{$i}", 'id' => '', 'class' => '', 'result_value_nls' => 'V'];
        }

        $out = $this->format([
            'rule_results' => [
                ['rule_id' => 'IMAGE_1', 'rule_summary' => 'Images need alt text', 'rule_required' => true, 'elements_violation' => 30, 'elements_warning' => 0, 'element_results' => $elements],
            ],
        ], cap: 25);

        $issue = $out['issues'][0];
        $this->assertCount(25, $issue['elements']);
        $this->assertTrue($issue['elements_truncated']);

        $kept = array_column($issue['elements'], 'identifier');
        $this->assertContains('ELEM_24', $kept);   // 25th (0-indexed) kept
        $this->assertNotContains('ELEM_25', $kept); // 26th dropped
    }

    /** @test */
    public function it_sorts_issues_by_violation_count_descending()
    {
        $out = $this->format([
            'rule_results' => [
                ['rule_id' => 'FEW', 'elements_violation' => 2, 'elements_warning' => 0],
                ['rule_id' => 'MANY', 'elements_violation' => 9, 'elements_warning' => 0],
                ['rule_id' => 'SOME', 'elements_violation' => 5, 'elements_warning' => 0],
            ],
        ]);

        $this->assertSame(['MANY', 'SOME', 'FEW'], array_column($out['issues'], 'rule_id'));
    }

    /** @test */
    public function it_returns_zero_issues_when_rule_results_missing_or_empty()
    {
        $this->assertSame(0, $this->format([])['issue_count']);
        $this->assertSame(0, $this->format(['rule_results' => []])['issue_count']);
        $this->assertSame([], $this->format([])['issues']);
    }

    /** @test */
    public function it_is_null_safe_for_missing_element_fields()
    {
        $out = $this->format([
            'rule_results' => [
                [
                    // No rule_summary / rule_required, element missing id/class.
                    'rule_id' => 'IMAGE_1',
                    'elements_violation' => 1,
                    'elements_warning' => 0,
                    'element_results' => [
                        ['element_identifier' => 'img: no-meta', 'result_value_nls' => 'V'],
                    ],
                ],
            ],
        ]);

        $issue = $out['issues'][0];
        $this->assertNull($issue['summary']);
        $this->assertNull($issue['required']);

        $element = $issue['elements'][0];
        $this->assertSame('img: no-meta', $element['identifier']);
        $this->assertNull($element['id']);
        $this->assertNull($element['class']);
        $this->assertSame('violation', $element['severity']);
    }

    /** @test */
    public function it_skips_malformed_non_array_rule_and_element_entries()
    {
        // Defensive: real prod payloads may contain nulls/scalars where arrays are
        // expected. These must be skipped, not throw a TypeError.
        $out = $this->format([
            'rule_results' => [
                null,
                'garbage',
                [
                    'rule_id' => 'IMAGE_1',
                    'elements_violation' => 1,
                    'elements_warning' => 0,
                    'element_results' => [
                        null,
                        'x',
                        ['element_identifier' => 'img: a', 'result_value_nls' => 'V'],
                    ],
                ],
            ],
        ]);

        $this->assertSame(1, $out['issue_count']);
        $this->assertCount(1, $out['issues'][0]['elements']);
        $this->assertSame('img: a', $out['issues'][0]['elements'][0]['identifier']);
    }

    /** @test */
    public function it_handles_a_rule_flagged_failing_but_missing_element_results()
    {
        // Counts say it fails, but no element_results array is present.
        $out = $this->format([
            'rule_results' => [
                ['rule_id' => 'IMAGE_1', 'elements_violation' => 3, 'elements_warning' => 0],
            ],
        ]);

        $issue = $out['issues'][0];
        $this->assertSame(1, $out['issue_count']);
        $this->assertSame([], $issue['elements']);
        $this->assertSame(3, $issue['elements_violation']);
        $this->assertFalse($issue['elements_truncated']);
    }
}
