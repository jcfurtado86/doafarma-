<?php

declare(strict_types = 1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Doctor>
 */
class DoctorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => 1,
            'crm'     => fake()->unique()->numerify('########'),
            'crm_uf'  => fake()->randomElement(['SP', 'RJ', 'MG', 'RS', 'PR', 'SC', 'DF', 'GO', 'MT', 'MS', 'AM', 'PA', 'BA', 'CE', 'MA', 'PB', 'PE', 'PI', 'RN', 'AL', 'ES', 'SE', 'TO', 'AC', 'AP', 'RR']),
        ];
    }
}
