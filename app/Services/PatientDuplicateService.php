<?php

namespace App\Services;

use App\Models\Patient;
use Illuminate\Support\Collection;

class PatientDuplicateService
{
    /**
     * Finds possible existing matches for a new registration, so the
     * receptionist can confirm "is this the same person?" before a new
     * patient_number is issued. This is intentionally loose (fuzzy) —
     * it surfaces candidates, it never blocks registration outright.
     */
    public function findPossibleMatches(array $data): Collection
    {
        $query = Patient::query()->whereNull('is_duplicate_of');

        $query->where(function ($q) use ($data) {
            // Strongest signal: national_id exact match
            if (! empty($data['national_id'])) {
                $q->orWhere('national_id', $data['national_id']);
            }

            // Phone match
            if (! empty($data['phone'])) {
                $q->orWhere('phone', $data['phone']);
            }

            // Same name + same date of birth
            if (! empty($data['first_name']) && ! empty($data['last_name']) && ! empty($data['date_of_birth'])) {
                $q->orWhere(function ($q2) use ($data) {
                    $q2->where('first_name', $data['first_name'])
                        ->where('last_name', $data['last_name'])
                        ->where('date_of_birth', $data['date_of_birth']);
                });
            }
        });

        return $query->limit(5)->get();
    }

    /**
     * Marks $duplicateId as a duplicate of $canonicalId.
     * The "losing" record is kept (for audit/history) but flagged,
     * not deleted — per the brief's merge-tracking requirement.
     */
    public function markAsDuplicate(int $duplicateId, int $canonicalId): void
    {
        Patient::whereKey($duplicateId)->update(['is_duplicate_of' => $canonicalId]);
    }
}
