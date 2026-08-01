<?php

namespace App\Services;

use App\Models\Patient;
use Illuminate\Support\Collection;

class PatientDuplicateService
{
    /**
     * Staged matching, per the mentor's specified order: date of birth
     * first (narrowest, most reliable signal), then name, then village.
     * Each stage only runs if the prior one found candidates — this is
     * closer to how a receptionist would actually narrow down "have I
     * seen this person before?" than a single OR-query across everything.
     * Falls back to national_id/phone as an immediate strong match at any
     * point, since those are more reliable than DOB when present.
     */
    public function findPossibleMatches(array $data): Collection
    {
        // Strongest possible signal: exact national ID or phone match,
        // checked first regardless of the DOB/name/village staging.
        if (! empty($data['national_id']) || ! empty($data['phone'])) {
            $strong = Patient::query()
                ->whereNull('is_duplicate_of')
                ->where(function ($q) use ($data) {
                    if (! empty($data['national_id'])) {
                        $q->orWhere('national_id', $data['national_id']);
                    }
                    if (! empty($data['phone'])) {
                        $q->orWhere('phone', $data['phone']);
                    }
                })
                ->limit(5)
                ->get();

            if ($strong->isNotEmpty()) {
                return $strong;
            }
        }

        if (empty($data['date_of_birth'])) {
            // No DOB to stage on — fall back to name-only, best effort.
            return $this->matchByName($data);
        }

        // Stage 1: date of birth.
        $byDob = Patient::query()
            ->whereNull('is_duplicate_of')
            ->where('date_of_birth', $data['date_of_birth'])
            ->get();

        if ($byDob->isEmpty()) {
            return collect();
        }

        // Stage 2: narrow by name within the DOB matches.
        if (! empty($data['first_name']) || ! empty($data['last_name'])) {
            $byName = $byDob->filter(function ($patient) use ($data) {
                $firstMatch = empty($data['first_name']) || str_contains(
                    strtolower($patient->first_name), strtolower($data['first_name'])
                );
                $lastMatch = empty($data['last_name']) || str_contains(
                    strtolower($patient->last_name), strtolower($data['last_name'])
                );

                return $firstMatch && $lastMatch;
            });

            if ($byName->isNotEmpty()) {
                $byDob = $byName;
            }
            // If nothing narrows by name, keep the DOB-only set — still a
            // real possible match worth surfacing rather than discarding.
        }

        // Stage 3: narrow further by village, if given and if it helps.
        if (! empty($data['village']) && $byDob->count() > 1) {
            $byVillage = $byDob->filter(
                fn ($patient) => $patient->village && str_contains(
                    strtolower($patient->village), strtolower($data['village'])
                )
            );

            if ($byVillage->isNotEmpty()) {
                $byDob = $byVillage;
            }
        }

        return $byDob->take(5)->values();
    }

    private function matchByName(array $data): Collection
    {
        if (empty($data['first_name']) && empty($data['last_name'])) {
            return collect();
        }

        return Patient::query()
            ->whereNull('is_duplicate_of')
            ->where(function ($q) use ($data) {
                if (! empty($data['first_name'])) {
                    $q->where('first_name', 'like', '%'.$data['first_name'].'%');
                }
                if (! empty($data['last_name'])) {
                    $q->where('last_name', 'like', '%'.$data['last_name'].'%');
                }
            })
            ->limit(5)
            ->get();
    }

    public function markAsDuplicate(int $duplicateId, int $canonicalId): void
    {
        Patient::whereKey($duplicateId)->update(['is_duplicate_of' => $canonicalId]);
    }
}
