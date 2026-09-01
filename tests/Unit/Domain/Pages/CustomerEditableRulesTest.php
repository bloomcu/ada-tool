<?php

namespace Tests\Unit\Domain\Pages;

use Tests\TestCase;
use DDD\Domain\Pages\CustomerEditableRules;

class CustomerEditableRulesTest extends TestCase
{
    /** @param array<int, mixed> $rules */
    private function results(array $rules): array
    {
        return ['rule_results' => $rules];
    }

    /** @test */
    public function it_flags_a_failing_customer_editable_rule()
    {
        $this->assertTrue(CustomerEditableRules::reviewable($this->results([
            ['rule_id' => 'HEADING_5', 'elements_violation' => 0, 'elements_warning' => 2],
        ])));
    }

    /** @test */
    public function it_does_not_flag_when_the_only_failing_rules_are_not_customer_editable()
    {
        // WIDGET_3 = structural; CONTROL_4 = deliberately excluded (site-wide banner/maps).
        $this->assertFalse(CustomerEditableRules::reviewable($this->results([
            ['rule_id' => 'WIDGET_3', 'elements_violation' => 3, 'elements_warning' => 0],
            ['rule_id' => 'CONTROL_4', 'elements_violation' => 1, 'elements_warning' => 0],
        ])));
    }

    /** @test */
    public function it_does_not_flag_a_customer_rule_that_is_not_failing()
    {
        $this->assertFalse(CustomerEditableRules::reviewable($this->results([
            ['rule_id' => 'HEADING_5', 'elements_violation' => 0, 'elements_warning' => 0],
        ])));
    }

    /** @test */
    public function it_is_null_and_malformed_safe()
    {
        $this->assertFalse(CustomerEditableRules::reviewable([]));
        $this->assertFalse(CustomerEditableRules::reviewable(['rule_results' => [null, 'garbage']]));
    }

    /** @test */
    public function annotate_flags_failing_editable_rules_and_floats_them_first()
    {
        $out = CustomerEditableRules::annotate(['rule_results' => [
            ['rule_id' => 'WIDGET_3', 'elements_violation' => 5, 'elements_warning' => 0],  // structural
            ['rule_id' => 'HEADING_5', 'elements_violation' => 0, 'elements_warning' => 1], // editable, failing
            ['rule_id' => 'LINK_1', 'elements_violation' => 0, 'elements_warning' => 0],     // editable but passing
        ]]);

        $rules = $out['rule_results'];

        // Editable + failing floats to the top and is flagged.
        $this->assertSame('HEADING_5', $rules[0]['rule_id']);
        $this->assertTrue($rules[0]['customer_reviewable']);

        // Everything else keeps original order (stable sort) and is not flagged
        // (structural, and a passing editable rule).
        $this->assertSame(['WIDGET_3', 'LINK_1'], [$rules[1]['rule_id'], $rules[2]['rule_id']]);
        $this->assertFalse($rules[1]['customer_reviewable']);
        $this->assertFalse($rules[2]['customer_reviewable']);
    }
}
