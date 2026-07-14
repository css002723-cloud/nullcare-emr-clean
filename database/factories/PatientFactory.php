<?php

namespace Database\Factories;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_number' => $this->faker->unique()->numerify('P####'),
            'national_id' => $this->faker->unique()->numerify('NID########'),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'gender' => $this->faker->randomElement(['Male', 'Female']),
            'date_of_birth' => $this->faker->date(),
            'age_estimate' => $this->faker->numberBetween(0, 120),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->unique()->safeEmail(),
            'address' => $this->faker->address(),
            'village' => $this->faker->word(),
            'traditional_authority' => $this->faker->word(),
            'district' => $this->faker->word(),
            'occupation' => $this->faker->word(),
            'patient_category' => $this->faker->randomElement(['outpatient', 'inpatient', 'emergency']),
            'guardian_name' => $this->faker->name(),
            'guardian_phone' => $this->faker->phoneNumber(),
            'guardian_relationship' => $this->faker->word(),
            'consent_care' => $this->faker->boolean(),
            'consent_teaching' => $this->faker->boolean(),
            'consent_research' => $this->faker->boolean(),
            'registered_by' => 1, // This can be set to a User ID when creating a patient
        ];
    }
}
