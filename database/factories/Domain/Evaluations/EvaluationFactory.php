<?php

namespace Database\Factories\Domain\Evaluations;

use Illuminate\Database\Eloquent\Factories\Factory;
use DDD\Domain\Sites\Site;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\DDD\Domain\Evaluations\Evaluation>
 */
class EvaluationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = \DDD\Domain\Evaluations\Evaluation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'site_id' => Site::factory(),
            'run_id' => $this->faker->uuid(),
            'queue_id' => $this->faker->uuid(),
            'dataset_id' => $this->faker->uuid(),
            'status' => 'READY',
        ];
    }
}
