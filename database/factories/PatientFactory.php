<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PatientFactory extends Factory
{
    protected $model = Patient::class;

    private array $districts = ['Blantyre', 'Zomba', 'Mzuzu', 'Lilongwe', 'Mangochi', 'Kasungu', 'Mulanje', 'Karonga'];
    private array $regions = ['Southern', 'Central', 'Northern'];
    private array $traditionalAuthorities = ['Kapeni', 'Chigaru', 'Kuntaja', 'Somba', 'Chimwala', 'Kadewere'];

    public function definition(): array
    {
        $sex = fake()->randomElement(['male', 'female']);
        $givenName = $sex === 'male' ? fake()->firstNameMale() : fake()->firstNameFemale();

        return [
            // Matches generate_patient_uid() in the Python reference: pure
            // random, no year/hospital prefix, no confusable characters.
            'patient_uid' => strtoupper(Str::random(9)),
            'national_id' => fake()->boolean(70) ? strtoupper(fake()->bothify('??######')) : null,
            'given_name' => $givenName,
            'family_name' => fake()->lastName(),
            'sex' => $sex,
            'date_of_birth' => fake()->boolean(85)
                ? fake()->dateTimeBetween('-90 years', '-1 years')->format('Y-m-d')
                : null,
            'estimated_age' => fake()->boolean(15) ? fake()->numberBetween(1, 90) : null,
            'phone' => fake()->boolean(80) ? '09'.fake()->numberBetween(10000000, 99999999) : null,
            'village' => fake()->boolean(70) ? fake()->citySuffix().' Village' : null,
            'traditional_authority' => fake()->randomElement($this->traditionalAuthorities),
            'district' => fake()->randomElement($this->districts),
            'region' => fake()->randomElement($this->regions),
            'occupation' => fake()->boolean(60) ? fake()->jobTitle() : null,
            'patient_category' => fake()->randomElement([
                'outpatient', 'outpatient', 'outpatient',
                'inpatient', 'emergency', 'student', 'staff', 'private', 'referred',
            ]),
            'guardian_name' => fake()->boolean(20) ? fake()->name() : null,
            'guardian_phone' => fake()->boolean(20) ? '09'.fake()->numberBetween(10000000, 99999999) : null,
            'guardian_relationship' => fake()->boolean(20) ? fake()->randomElement(['mother', 'father', 'spouse', 'sibling', 'guardian']) : null,
            'consent_care' => true,
            'consent_teaching' => fake()->boolean(30),
            'consent_research' => fake()->boolean(15),
            'is_deceased' => false,
            'registered_by' => User::inRandomOrder()->value('id') ?? User::factory(),
        ];
    }

    public function child(): static
    {
        return $this->state(fn () => [
            'date_of_birth' => fake()->dateTimeBetween('-11 years', '-1 years')->format('Y-m-d'),
            'guardian_name' => fake()->name(),
            'guardian_phone' => '09'.fake()->numberBetween(10000000, 99999999),
            'guardian_relationship' => fake()->randomElement(['mother', 'father', 'guardian']),
        ]);
    }

    public function deceased(): static
    {
        return $this->state(fn () => ['is_deceased' => true, 'date_of_death' => now()]);
    }

    /** Adult 18-64, DOB always known — useful when a test needs a predictable non-pediatric patient. */
    public function adult(): static
    {
        return $this->state(fn () => [
            'date_of_birth' => fake()->dateTimeBetween('-64 years', '-18 years')->format('Y-m-d'),
            'estimated_age' => null,
        ]);
    }

    /** 65+, DOB always known — for age-related clinical logic (e.g. dosing, chronic disease flows). */
    public function elderly(): static
    {
        return $this->state(fn () => [
            'date_of_birth' => fake()->dateTimeBetween('-95 years', '-65 years')->format('Y-m-d'),
            'estimated_age' => null,
        ]);
    }

    /**
     * No national ID, no known DOB — only an estimated age. Exercises the
     * is_pediatric()/registration fallback path and Reception/Records
     * "incomplete demographics" edge cases.
     */
    public function undocumented(): static
    {
        return $this->state(fn () => [
            'national_id' => null,
            'date_of_birth' => null,
            'estimated_age' => fake()->numberBetween(1, 90),
            'phone' => null,
            'village' => null,
        ]);
    }
}