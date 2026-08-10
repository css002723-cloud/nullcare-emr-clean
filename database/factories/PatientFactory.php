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
    private array $maleNames = ['Limbani', 'Tiyanjane', 'Chisomo', 'Yamikani', 'Kondwani', 'Mwayi', 'Takondwa', 'Gift', 'Blessings', 'Dumbani'];
    private array $femaleNames = ['Tadala', 'Chifuniro', 'Thokozani', 'Chikondi', 'Mercy', 'Patience', 'Grace', 'Chikumbutso', 'Favour', 'Tiwonge'];
    private array $familyNames = ['Phiri', 'Banda', 'Mwale', 'Tembo', 'Chirwa', 'Gondwe', 'Sambo', 'Nkosi', 'Kamanga', 'Moyo'];
    private array $occupations = ['Farmer', 'Teacher', 'Trader', 'Civil Servant', 'Business Person', 'Driver', 'Student', 'Tailor', 'Nurse'];

    public function definition(): array
    {
        $sex = $this->pick(['male', 'female']);
        $givenName = $sex === 'male' ? $this->pick($this->maleNames) : $this->pick($this->femaleNames);

        return [
            'patient_uid' => strtoupper(Str::random(9)),
            'national_id' => $this->chance(70) ? $this->nationalId() : null,
            'given_name' => $givenName,
            'family_name' => $this->pick($this->familyNames),
            'sex' => $sex,
            'date_of_birth' => $this->chance(85) ? $this->dateBetween('-90 years', '-1 years') : null,
            'estimated_age' => $this->chance(15) ? rand(1, 90) : null,
            'phone' => $this->chance(80) ? '09'.rand(10000000, 99999999) : null,
            'village' => $this->chance(70) ? $this->pick($this->districts).' Village' : null,
            'traditional_authority' => $this->pick($this->traditionalAuthorities),
            'district' => $this->pick($this->districts),
            'region' => $this->pick($this->regions),
            'occupation' => $this->chance(60) ? $this->pick($this->occupations) : null,
            'patient_category' => $this->pick([
                'outpatient', 'outpatient', 'outpatient',
                'inpatient', 'emergency', 'student', 'staff', 'private', 'referred',
            ]),
            'guardian_name' => $this->chance(20) ? $this->pick($this->maleNames).' '.$this->pick($this->familyNames) : null,
            'guardian_phone' => $this->chance(20) ? '09'.rand(10000000, 99999999) : null,
            'guardian_relationship' => $this->chance(20) ? $this->pick(['mother', 'father', 'spouse', 'sibling', 'guardian']) : null,
            'consent_care' => true,
            'consent_teaching' => $this->chance(30),
            'consent_research' => $this->chance(15),
            'is_deceased' => false,
            'registered_by' => User::inRandomOrder()->value('id') ?? User::factory(),
        ];
    }

    public function child(): static
    {
        return $this->state(fn () => [
            'date_of_birth' => $this->dateBetween('-11 years', '-1 years'),
            'guardian_name' => $this->pick($this->maleNames).' '.$this->pick($this->familyNames),
            'guardian_phone' => '09'.rand(10000000, 99999999),
            'guardian_relationship' => $this->pick(['mother', 'father', 'guardian']),
        ]);
    }

    public function deceased(): static
    {
        return $this->state(fn () => ['is_deceased' => true, 'date_of_death' => now()]);
    }

    public function adult(): static
    {
        return $this->state(fn () => [
            'date_of_birth' => $this->dateBetween('-64 years', '-18 years'),
            'estimated_age' => null,
        ]);
    }

    public function elderly(): static
    {
        return $this->state(fn () => [
            'date_of_birth' => $this->dateBetween('-95 years', '-65 years'),
            'estimated_age' => null,
        ]);
    }

    public function undocumented(): static
    {
        return $this->state(fn () => [
            'national_id' => null,
            'date_of_birth' => null,
            'estimated_age' => rand(1, 90),
            'phone' => null,
            'village' => null,
        ]);
    }

    // --- Native Helpers (Zero dependencies on Faker) ---

    private function pick(array $items): mixed
    {
        return $items[array_rand($items)];
    }

    private function chance(int $percentage): bool
    {
        return rand(1, 100) <= $percentage;
    }

    private function dateBetween(string $start, string $end): string
    {
        $min = strtotime($start);
        $max = strtotime($end);

        return date('Y-m-d', rand($min, $max));
    }

    private function nationalId(): string
    {
        $letters = chr(rand(65, 90)).chr(rand(65, 90));

        return $letters.sprintf('%06d', rand(0, 999999));
    }
}