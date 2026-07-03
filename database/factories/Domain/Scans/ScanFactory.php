<?php

namespace Database\Factories\Domain\Scans;

use Illuminate\Database\Eloquent\Factories\Factory;
use DDD\Domain\Sites\Site;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\DDD\Domain\Scans\Scan>
 */
class ScanFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = \DDD\Domain\Scans\Scan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        // Create the site up front so the scan and its site share an organization.
        $site = Site::factory()->create();

        return [
            'organization_id' => $site->organization_id,
            'site_id' => $site->id,
            'run_id' => $this->faker->uuid(),
            'queue_id' => $this->faker->uuid(),
            'dataset_id' => $this->faker->uuid(),
            'status' => 'READY',
            // count columns are nullable; is_single_page defaults to false.
        ];
    }
}
