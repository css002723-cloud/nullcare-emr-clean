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
        $sex = $this->faker->randomElement(['male', 'female']);
        $givenName = $sex === 'male' ? $this->faker->firstNameMale() : $this->faker->firstNameFemale();

        return [
            // Matches generate_patient_uid() in the Python reference: pure
            // random, no year/hospital prefix, no confusable characters.
            'patient_uid' => strtoupper(Str::random(9)),
            'national_id' => $this->faker->boolean(70) ? strtoupper($this->faker->bothify('??######')) : null,
            'given_name' => $givenName,
            'family_name' => $this->faker->lastName(),
            'sex' => $sex,
            'date_of_birth' => $this->faker->boolean(85)
                ? $this->faker->dateTimeBetween('-90 years', '-1 years')->format('Y-m-d')
                : null,
            'estimated_age' => $this->faker->boolean(15) ? $this->faker->numberBetween(1, 90) : null,
            'phone' => $this->faker->boolean(80) ? '09'.$this->faker->numberBetween(10000000, 99999999) : null,
            'village' => $this->faker->boolean(70) ? $this->faker->citySuffix().' Village' : null,
            'traditional_authority' => $this->faker->randomElement($this->traditionalAuthorities),
            'district' => $this->faker->randomElement($this->districts),
            'region' => $this->faker->randomElement($this->regions),
            'occupation' => $this->faker->boolean(60) ? $this->faker->jobTitle() : null,
            'patient_category' => $this->faker->randomElement([
                'outpatient', 'outpatient', 'outpatient',
                'inpatient', 'emergency', 'student', 'staff', 'private', 'referred',
            ]),
            'guardian_name' => $this->faker->boolean(20) ? $this->faker->name() : null,
            'guardian_phone' => $this->faker->boolean(20) ? '09'.$this->faker->numberBetween(10000000, 99999999) : null,
            'guardian_relationship' => $this->faker->boolean(20) ? $this->faker->randomElement(['mother', 'father', 'spouse', 'sibling', 'guardian']) : null,
            'consent_care' => true,
            'consent_teaching' => $this->faker->boolean(30),
            'consent_research' => $this->faker->boolean(15),
            'is_deceased' => false,
            'registered_by' => User::inRandomOrder()->value('id'),
        ];
    }

    public function child(): static
    {
        return $this->state(fn () => [
            'date_of_birth' => $this->faker->dateTimeBetween('-11 years', '-1 years')->format('Y-m-d'),
            'guardian_name' => $this->faker->name(),
            'guardian_phone' => '09'.$this->faker->numberBetween(10000000, 99999999),
            'guardian_relationship' => $this->faker->randomElement(['mother', 'father', 'guardian']),
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
            'date_of_birth' => $this->faker->dateTimeBetween('-64 years', '-18 years')->format('Y-m-d'),
            'estimated_age' => null,
        ]);
    }

    /** 65+, DOB always known — for age-related clinical logic (e.g. dosing, chronic disease flows). */
    public function elderly(): static
    {
        return $this->state(fn () => [
            'date_of_birth' => $this->faker->dateTimeBetween('-95 years', '-65 years')->format('Y-m-d'),
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
            'estimated_age' => $this->faker->numberBetween(1, 90),
            'phone' => null,
            'village' => null,
        ]);
    }
}
