<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tasks>
 */
class TasksFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'status' => $this->faker->randomElement(['pending', 'in_progress', 'completed']),
            'due_date' => $this->faker->dateTimeBetween('now', '+1 month'),
            'user_id' => function () {
                return \App\Models\User::factory()->create()->id;
            },
            'project_id' => function () {
                return \App\Models\Projects::factory()->create()->id;
            },
            'assigned_to' => function () {
                return \App\Models\User::factory()->create()->id;
            },
            'created_by' => function () {
                return \App\Models\User::factory()->create()->id;
            },
        ];
    }
}
