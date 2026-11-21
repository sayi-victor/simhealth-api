<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\Bed;
use App\Models\Immunization;
use App\Models\Surgery;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClinicalModulesService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    // ─── Inpatient Admissions ───────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Admission>
     */
    public function listAdmissions(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 25), 1), 100);
        $query = Admission::query()->with([
            'patient:id,mrn,title,first_name,middle_name,last_name',
            'ward:id,code,name,category,daily_rate',
            'bed:id,ward_id,bed_number,status',
            'admittingProvider:id,title,first_name,middle_name,last_name,suffix',
        ]);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['ward_id'])) {
            $query->where('ward_id', (int) $filters['ward_id']);
        }
        if (! empty($filters['patient_id'])) {
            $query->where('patient_id', (int) $filters['patient_id']);
        }

        return $query->orderByDesc('admitted_at')->paginate($perPage);
    }

    /** @param array<string, mixed> $data */
    public function admit(array $data, User $actor, Request $request): Admission
    {
        return DB::transaction(function () use ($data, $actor, $request) {
            $bed = Bed::query()->lockForUpdate()->findOrFail($data['bed_id']);

            if ($bed->status !== 'available') {
                throw ValidationException::withMessages([
                    'bed_id' => ["Bed {$bed->bed_number} is currently {$bed->status} and unavailable."],
                ]);
            }

            $activeForPatient = Admission::query()
                ->where('patient_id', $data['patient_id'])
                ->where('status', 'admitted')
                ->exists();

            if ($activeForPatient) {
                throw ValidationException::withMessages([
                    'patient_id' => ['Patient already has an active inpatient admission.'],
                ]);
            }

            $admission = Admission::create(array_merge($data, [
                'admission_number' => 'TMP-'.Str::uuid()->toString(),
                'ward_id' => $bed->ward_id,
                'facility_id' => $data['facility_id'] ?? $actor->facility_id,
                'admitting_provider_id' => $data['admitting_provider_id'] ?? $actor->id,
                'admitted_at' => $data['admitted_at'] ?? now(),
                'status' => 'admitted',
            ]));

            $admission->forceFill([
                'admission_number' => sprintf('ADM-%06d', $admission->id),
            ])->save();

            $bed->forceFill(['status' => 'occupied'])->save();

            $this->audit->log(
                event: 'inpatient.admitted',
                category: 'inpatient',
                user: $actor,
                auditable: $admission,
                success: true,
                metadata: ['admission_id' => $admission->id, 'bed_id' => $bed->id],
                request: $request,
            );

            return $admission->fresh(['patient', 'ward', 'bed', 'admittingProvider']);
        });
    }

    /** @param array<string, mixed> $data */
    public function transferBed(Admission $admission, array $data, User $actor, Request $request): Admission
    {
        return DB::transaction(function () use ($admission, $data, $actor, $request) {
            if ($admission->status !== 'admitted') {
                throw ValidationException::withMessages([
                    'status' => ['Only active admissions can be transferred.'],
                ]);
            }

            $newBed = Bed::query()->lockForUpdate()->findOrFail($data['bed_id']);
            if ($newBed->status !== 'available') {
                throw ValidationException::withMessages([
                    'bed_id' => ["Target bed {$newBed->bed_number} is not available."],
                ]);
            }

            if ($admission->bed_id) {
                Bed::where('id', $admission->bed_id)->update(['status' => 'available']);
            }

            $newBed->forceFill(['status' => 'occupied'])->save();
            $admission->forceFill([
                'ward_id' => $newBed->ward_id,
                'bed_id' => $newBed->id,
            ])->save();

            $this->audit->log(
                event: 'inpatient.transferred',
                category: 'inpatient',
                user: $actor,
                auditable: $admission,
                success: true,
                metadata: ['admission_id' => $admission->id, 'new_bed_id' => $newBed->id],
                request: $request,
            );

            return $admission->fresh(['patient', 'ward', 'bed', 'admittingProvider']);
        });
    }

    /** @param array<string, mixed> $data */
    public function discharge(Admission $admission, array $data, User $actor, Request $request): Admission
    {
        return DB::transaction(function () use ($admission, $data, $actor, $request) {
            if ($admission->status !== 'admitted') {
                throw ValidationException::withMessages([
                    'status' => ['Admission is not active.'],
                ]);
            }

            if ($admission->bed_id) {
                Bed::where('id', $admission->bed_id)->update(['status' => 'available']);
            }

            $admission->forceFill([
                'status' => 'discharged',
                'discharged_at' => $data['discharged_at'] ?? now(),
                'discharging_provider_id' => $actor->id,
                'discharge_diagnosis' => $data['discharge_diagnosis'] ?? null,
                'discharge_summary' => $data['discharge_summary'] ?? null,
                'discharge_disposition' => $data['discharge_disposition'] ?? 'home',
            ])->save();

            $this->audit->log(
                event: 'inpatient.discharged',
                category: 'inpatient',
                user: $actor,
                auditable: $admission,
                success: true,
                metadata: ['admission_id' => $admission->id, 'disposition' => $admission->discharge_disposition],
                request: $request,
            );

            return $admission->fresh(['patient', 'ward', 'bed', 'admittingProvider', 'dischargingProvider']);
        });
    }

    // ─── Surgery ────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Surgery>
     */
    public function listSurgeries(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 25), 1), 100);
        $query = Surgery::query()->with([
            'patient:id,mrn,title,first_name,middle_name,last_name',
            'surgeon:id,title,first_name,middle_name,last_name,suffix',
            'anesthetist:id,title,first_name,middle_name,last_name,suffix',
        ]);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['patient_id'])) {
            $query->where('patient_id', (int) $filters['patient_id']);
        }

        return $query->orderByDesc('scheduled_at')->paginate($perPage);
    }

    /** @param array<string, mixed> $data */
    public function scheduleSurgery(array $data, User $actor, Request $request): Surgery
    {
        $surgery = Surgery::create(array_merge($data, [
            'surgery_number' => 'TMP-'.Str::uuid()->toString(),
            'facility_id' => $data['facility_id'] ?? $actor->facility_id,
            'surgeon_id' => $data['surgeon_id'] ?? $actor->id,
            'status' => 'scheduled',
        ]));

        $surgery->forceFill([
            'surgery_number' => sprintf('SRG-%06d', $surgery->id),
        ])->save();

        $this->audit->log(
            event: 'surgery.scheduled',
            category: 'surgery',
            user: $actor,
            auditable: $surgery,
            success: true,
            metadata: ['surgery_id' => $surgery->id],
            request: $request,
        );

        return $surgery->fresh(['patient', 'surgeon', 'anesthetist']);
    }

    /** @param array<string, mixed> $data */
    public function completeSurgery(Surgery $surgery, array $data, User $actor, Request $request): Surgery
    {
        $surgery->forceFill([
            'status' => 'completed',
            'started_at' => $data['started_at'] ?? $surgery->started_at ?? now(),
            'completed_at' => $data['completed_at'] ?? now(),
            'post_op_diagnosis' => $data['post_op_diagnosis'] ?? null,
            'operative_notes' => $data['operative_notes'] ?? null,
            'complications' => $data['complications'] ?? null,
        ])->save();

        $this->audit->log(
            event: 'surgery.completed',
            category: 'surgery',
            user: $actor,
            auditable: $surgery,
            success: true,
            metadata: ['surgery_id' => $surgery->id],
            request: $request,
        );

        return $surgery->fresh(['patient', 'surgeon', 'anesthetist']);
    }

    // ─── Immunizations / Vaccination ────────────────────────────────────

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Immunization>
     */
    public function listImmunizations(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 25), 1), 100);
        $query = Immunization::query()->with([
            'patient:id,mrn,title,first_name,middle_name,last_name',
            'administrator:id,title,first_name,middle_name,last_name,suffix',
        ]);

        if (! empty($filters['patient_id'])) {
            $query->where('patient_id', (int) $filters['patient_id']);
        }
        if (! empty($filters['vaccine_name'])) {
            $query->where('vaccine_name', 'like', '%'.$filters['vaccine_name'].'%');
        }

        return $query->orderByDesc('administered_at')->paginate($perPage);
    }

    /** @param array<string, mixed> $data */
    public function recordImmunization(array $data, User $actor, Request $request): Immunization
    {
        $imm = Immunization::create(array_merge($data, [
            'facility_id' => $data['facility_id'] ?? $actor->facility_id,
            'administered_by' => $data['administered_by'] ?? $actor->id,
            'administered_at' => $data['administered_at'] ?? now(),
        ]));

        $this->audit->log(
            event: 'immunizations.recorded',
            category: 'vaccination',
            user: $actor,
            auditable: $imm,
            success: true,
            metadata: ['immunization_id' => $imm->id, 'patient_id' => $imm->patient_id],
            request: $request,
        );

        return $imm->fresh(['patient', 'administrator']);
    }
}
