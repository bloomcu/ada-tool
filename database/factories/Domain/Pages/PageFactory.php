<?php

namespace Database\Factories\Domain\Pages;

use Illuminate\Database\Eloquent\Factories\Factory;
use DDD\Domain\Scans\Scan;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\DDD\Domain\Pages\Page>
 */
class PageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = \DDD\Domain\Pages\Page::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        // `results` is a NOT NULL json column; the Page model json_decodes it on read.
        return [
            'scan_id' => Scan::factory(),
            'title' => $this->faker->sentence(3),
            'results' => json_encode([
                'violations' => [],
                'warnings' => [],
            ]),
            // violation_count / warning_count / rescan_id are nullable.
        ];
    }
}
