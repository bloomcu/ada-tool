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
}
