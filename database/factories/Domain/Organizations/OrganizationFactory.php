<?php

namespace Database\Factories\Domain\Organizations;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\DDD\Domain\Organizations\Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * Uses the product Organization (which adds sites()/scans() on top of the
     * base model). It shares the `organizations` table and resolves to this
     * factory via the default namespace guesser.
     *
     * @var string
     */
    protected $model = \DDD\Domain\Organizations\Organization::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'title' => $this->faker->company
        ];
    }
}
