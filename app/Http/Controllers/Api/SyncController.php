<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClinicalNote;
use App\Models\Encounter;
use App\Models\LabOrder;
use App\Models\Order;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Referral;
use App\Models\Vital;
use App\Services\AuditLogger;
use App\Services\IdGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
{
    /**
     * Field allow-lists per resource type — matches RESOURCE_MODELS /
     * per-branch payload filtering in sync.py exactly. Anything not
     * listed here in an offline-queued payload is silently dropped,
     * same as the reference.
     */
    private const ALLOWED_FIELDS = [
        'patient' => [
            'national_id', 'given_name', 'family_name', 'sex', 'estimated_age',
            'phone', 'village', 'traditional_authority', 'district', 'region',
            'occupation', 'guardian_name', 'guardian_relationship', 'guardian_phone',
            'patient_category',
        ],
        'encounter' => ['patient_id', 'visit_type', 'priority', 'chief_complaint', 'current_department'],
        'vitals' => [
            'encounter_id', 'patient_id', 'temperature_c', 'blood_pressure_systolic',
            'blood_pressure_diastolic', 'pulse_rate', 'respiratory_rate', 'spo2',
            'weight_kg', 'height_cm', 'pain_score', 'blood_glucose', 'gcs',
        ],
        'note' => [
            'encounter_id', 'patient_id', 'note_type', 'presenting_complaint', 'history',
            'examination_findings', 'diagnosis', 'icd_code', 'differential_diagnosis', 'plan', 'body',
        ],
        'order' => ['encounter_id', 'patient_id', 'order_type', 'details', 'priority', 'target_department'],
        'prescription' => ['encounter_id', 'patient_id', 'drug_name', 'formulation', 'dose', 'route', 'frequency', 'duration'],
        'referral' => ['encounter_id', 'patient_id', 'from_department', 'to_department', 'reason', 'priority'],
    ];

    /**
     * GET /api/sync/status
     */
    public function status()
    {
        return response()->json(['server_time' => now()->toIso8601String(), 'status' => 'online']);
    }

    /**
     * POST /api/sync/push
     * Body: { items: [{ resource_type, client_uuid, payload }, ...] }
     * Replays each queued offline write against the same create logic the
     * normal REST routes use, keyed by client_uuid so a retried sync
     * (connection drops mid-batch) never creates duplicates.
     */
    public function push(Request $request)
    {
        $items = $request->input('items', []);
        $user = $request->user();
        $results = [];

        foreach ($items as $item) {
            $resourceType = $item['resource_type'] ?? null;
            $clientUuid = $item['client_uuid'] ?? null;
            $payload = $item['payload'] ?? [];

            if (! isset(self::ALLOWED_FIELDS[$resourceType]) && $resourceType !== 'lab_order') {
                $results[] = ['client_uuid' => $clientUuid, 'status' => 'error', 'message' => 'unknown resource_type'];

                continue;
            }

            $modelClass = match ($resourceType) {
                'patient' => Patient::class,
                'encounter' => Encounter::class,
                'vitals' => Vital::class,
                'note' => ClinicalNote::class,
                'order' => Order::class,
                'prescription' => Prescription::class,
                'lab_order' => LabOrder::class,
                'referral' => Referral::class,
                default => null,
            };

            $existing = $modelClass::where('client_uuid', $clientUuid)->first();
            if ($existing) {
                $results[] = ['client_uuid' => $clientUuid, 'status' => 'already_synced', 'server_id' => $existing->id];

                continue;
            }

            try {
                $object = DB::transaction(function () use ($resourceType, $payload, $clientUuid, $user) {
                    $filtered = array_intersect_key($payload, array_flip(self::ALLOWED_FIELDS[$resourceType] ?? []));

                    return match ($resourceType) {
                        'patient' => Patient::create([
                            ...$filtered,
                            'patient_uid' => $this->uniquePatientUid(),
                            'client_uuid' => $clientUuid,
                            'registered_by' => $user->id,
                        ]),
                        'encounter' => Encounter::create([
                            ...$filtered,
                            'is_emergency' => ($filtered['priority'] ?? null) === 'emergency' || ($filtered['visit_type'] ?? null) === 'emergency',
                            'encounter_number' => IdGenerator::encounterNumber(),
                            'mrn' => $this->uniqueMrn(),
                            'client_uuid' => $clientUuid,
                            'registered_by' => $user->id,
                        ]),
                        'vitals' => Vital::create([...$filtered, 'client_uuid' => $clientUuid, 'recorded_by' => $user->id]),
                        'note' => ClinicalNote::create([
                            ...$filtered, 'client_uuid' => $clientUuid,
                            'author_id' => $user->id, 'author_role' => $user->role,
                        ]),
                        'order' => Order::create([...$filtered, 'client_uuid' => $clientUuid, 'ordered_by' => $user->id]),
                        'prescription' => Prescription::create([...$filtered, 'client_uuid' => $clientUuid, 'prescribed_by' => $user->id]),
                        'referral' => Referral::create([...$filtered, 'client_uuid' => $clientUuid, 'referred_by' => $user->id]),
                        default => throw new \RuntimeException('unsupported'),
                    };
                });

                AuditLogger::log($user, "offline_sync_{$resourceType}", $resourceType, $object->id, 'synced from offline queue');

                $results[] = ['client_uuid' => $clientUuid, 'status' => 'synced', 'server_id' => $object->id];
            } catch (\Throwable $e) {
                $results[] = ['client_uuid' => $clientUuid, 'status' => 'error', 'message' => $e->getMessage()];
            }
        }

        return response()->json([
            'results' => $results,
            'synced_count' => count(array_filter($results, fn ($r) => $r['status'] === 'synced')),
        ]);
    }

    private function uniquePatientUid(): string
    {
        do {
            $uid = IdGenerator::patientUid();
        } while (Patient::where('patient_uid', $uid)->exists());

        return $uid;
    }

    private function uniqueMrn(): string
    {
        do {
            $mrn = IdGenerator::mrn();
        } while (Encounter::where('mrn', $mrn)->exists());

        return $mrn;
    }
}
