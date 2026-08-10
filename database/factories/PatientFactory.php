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
        // Safe Faker instance (works in all Laravel versions & Docker environments)
        $faker = $this->faker ?? \Faker\Factory::create();

        $sex = $faker->randomElement(['male', 'female']);
        $givenName = $sex === 'male' ? $faker->firstNameMale() : $faker->firstNameFemale();

        return [
            // Matches generate_patient_uid() in the Python reference: pure
            // random, no year/hospital prefix, no confusable characters.
            'patient_uid' => strtoupper(Str::random(9)),
            'national_id' => $faker->boolean(70) ? strtoupper($faker->bothify('??######')) : null,
            'given_name' => $givenName,
            'family_name' => $faker->lastName(),
            'sex' => $sex,
            'date_of_birth' => $faker->boolean(85)
                ? $faker->dateTimeBetween('-90 years', '-1 years')->format('Y-m-d')
                : null,
            'estimated_age' => $faker->boolean(15) ? $faker->numberBetween(1, 90) : null,
            'phone' => $faker->boolean(80) ? '09'.$faker->numberBetween(10000000, 99999999) : null,
            'village' => $faker->boolean(70) ? $faker->citySuffix().' Village' : null,
            'traditional_authority' => $faker->randomElement($this->traditionalAuthorities),
            'district' => $faker->randomElement($this->districts),
            'region' => $faker->randomElement($this->regions),
            'occupation' => $faker->boolean(60) ? $faker->jobTitle() : null,
            'patient_category' => $faker->randomElement([
                'outpatient', 'outpatient', 'outpatient',
                'inpatient', 'emergency', 'student', 'staff', 'private', 'referred',
            ]),
            'guardian_name' => $faker->boolean(20) ? $faker->name() : null,
            'guardian_phone' => $faker->boolean(20) ? '09'.$faker->numberBetween(10000000, 99999999) : null,
            'guardian_relationship' => $faker->boolean(20) ? $faker->randomElement(['mother', 'father', 'spouse', 'sibling', 'guardian']) : null,
            'consent_care' => true,
            'consent_teaching' => $faker->boolean(30),
            'consent_research' => $faker->boolean(15),
            'is_deceased' => false,
            'registered_by' => User::inRandomOrder()->value('id') ?? User::factory(),
        ];
    }

    public function child(): static
    {
        $faker = $this->faker ?? \Faker\Factory::create();

        return $this->state(fn () => [
            'date_of_birth' => $faker->dateTimeBetween('-11 years', '-1 years')->format('Y-m-d'),
            'guardian_name' => $faker->name(),
            'guardian_phone' => '09'.$faker->numberBetween(10000000, 99999999),
            'guardian_relationship' => $faker->randomElement(['mother', 'father', 'guardian']),
        ]);
    }

    public function deceased(): static
    {
        return $this->state(fn () => ['is_deceased' => true, 'date_of_death' => now()]);
    }

    /** Adult 18-64, DOB always known — useful when a test needs a predictable non-pediatric patient. */
    public function adult(): static
    {
        $faker = $this->faker ?? \Faker\Factory::create();

        return $this->state(fn () => [
            'date_of_birth' => $faker->dateTimeBetween('-64 years', '-18 years')->format('Y-m-d'),
            'estimated_age' => null,
        ]);
    }

    /** 65+, DOB always known — for age-related clinical logic (e.g. dosing, chronic disease flows). */
    public function elderly(): static
    {
        $faker = $this->faker ?? \Faker\Factory::create();

        return $this->state(fn () => [
            'date_of_birth' => $faker->dateTimeBetween('-95 years', '-65 years')->format('Y-m-d'),
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
        $faker = $this->faker ?? \Faker\Factory::create();

        return $this->state(fn () => [
            'national_id' => null,
            'date_of_birth' => null,
            'estimated_age' => $faker->numberBetween(1, 90),
            'phone' => null,
            'village' => null,
        ]);
    }
}