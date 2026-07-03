<?php

namespace Database\Factories\Domain\Sites;

use Illuminate\Database\Eloquent\Factories\Factory;
use DDD\Domain\Organizations\Organization;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\DDD\Domain\Sites\Site>
 */
class SiteFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = \DDD\Domain\Sites\Site::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $domain = $this->faker->unique()->domainName();

        return [
            'organization_id' => Organization::factory(),
            'title' => $this->faker->company(),
            'url' => 'https://' . $domain,
            'domain' => $domain,
            'scheme' => 'https',
            // scan_schedule ('manual'), include_3pi (true) have DB defaults.
        ];
    }
}
