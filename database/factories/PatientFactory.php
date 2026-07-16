<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PatientFactory extends Factory
{
    protected $model = Patient::class;

    /**
     * Sample Malawian locations for realistic-looking test data, matching
     * the district/region/village/TA fields your schema actually tracks.
     */
    private array $districts = ['Blantyre', 'Zomba', 'Mzuzu', 'Lilongwe', 'Mangochi', 'Kasungu', 'Mulanje', 'Karonga'];
    private array $regions = ['Southern', 'Central', 'Northern'];
    private array $traditionalAuthorities = ['Kapeni', 'Chigaru', 'Kuntaja', 'Somba', 'Chimwala', 'Kadewere'];

    public function definition(): array
    {
        $gender = $this->faker->randomElement(['male', 'female']);
        $firstName = $gender === 'male' ? $this->faker->firstNameMale() : $this->faker->firstNameFemale();

        return [
            'patient_number' => 'NC-'.now()->format('Y').'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'national_id' => $this->faker->boolean(70) ? strtoupper($this->faker->bothify('??######')) : null,
            'first_name' => $firstName,
            'last_name' => $this->faker->lastName(),
            'gender' => $gender,
            'date_of_birth' => $this->faker->boolean(85)
                ? $this->faker->dateTimeBetween('-90 years', '-1 years')->format('Y-m-d')
                : null,
            'age_estimate' => $this->faker->boolean(15) ? $this->faker->numberBetween(1, 90) : null,
            'phone' => $this->faker->boolean(80) ? '09'.$this->faker->numberBetween(10000000, 99999999) : null,
            'email' => $this->faker->boolean(20) ? $this->faker->safeEmail() : null,
            'village' => $this->faker->boolean(70) ? $this->faker->citySuffix().' Village' : null,
            'traditional_authority' => $this->faker->randomElement($this->traditionalAuthorities),
            'district' => $this->faker->randomElement($this->districts),
            'region' => $this->faker->randomElement($this->regions),
            'occupation' => $this->faker->boolean(60) ? $this->faker->jobTitle() : null,
            'patient_category' => $this->faker->randomElement([
                'outpatient', 'outpatient', 'outpatient', // weighted — most visits are outpatient
                'inpatient', 'emergency', 'student', 'staff', 'private', 'referred',
            ]),
            'guardian_name' => $this->faker->boolean(20) ? $this->faker->name() : null,
            'guardian_phone' => $this->faker->boolean(20) ? '09'.$this->faker->numberBetween(10000000, 99999999) : null,
            'guardian_relationship' => $this->faker->boolean(20) ? $this->faker->randomElement(['mother', 'father', 'spouse', 'sibling', 'guardian']) : null,
            'consent_care' => true,
            'consent_teaching' => $this->faker->boolean(30),
            'consent_research' => $this->faker->boolean(15),
            'is_deceased' => false,
            'completion_status' => $this->faker->randomElement(['not_completed', 'not_completed', 'completed']),
            'registered_by' => User::inRandomOrder()->value('id') ?? 1,
        ];
    }

    /**
     * Usage: Patient::factory()->child()->create()
     */
    public function child(): static
    {
        return $this->state(fn () => [
            'date_of_birth' => $this->faker->dateTimeBetween('-11 years', '-1 years')->format('Y-m-d'),
            'guardian_name' => $this->faker->name(),
            'guardian_phone' => '09'.$this->faker->numberBetween(10000000, 99999999),
            'guardian_relationship' => $this->faker->randomElement(['mother', 'father', 'guardian']),
        ]);
    }

    /**
     * Usage: Patient::factory()->deceased()->create()
     */
    public function deceased(): static
    {
        return $this->state(fn () => ['is_deceased' => true]);
    }
}