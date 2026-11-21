<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\PatientIssue;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PatientService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Patient>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 25), 1), 100);
        $search = trim((string) ($filters['search'] ?? ''));
        $sort = (string) ($filters['sort'] ?? 'created_at');
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query = Patient::query()->with(['facility:id,name,code']);

        if (! empty($filters['archived']) && filter_var($filters['archived'], FILTER_VALIDATE_BOOLEAN)) {
            $query->onlyTrashed();
        }

        if (! empty($filters['sex'])) {
            $query->where('sex', $filters['sex']);
        }

        if (! empty($filters['facility_id'])) {
            $query->where('facility_id', (int) $filters['facility_id']);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $like = "%{$search}%";
                $q->where('mrn', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('middle_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('phone_cell', 'like', $like)
                    ->orWhere('national_id', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        match ($sort) {
            'name' => $query->orderBy('last_name', $direction)
                ->orderBy('first_name', $direction)
                ->orderBy('middle_name', $direction),
            'mrn' => $query->orderBy('mrn', $direction),
            'date_of_birth' => $query->orderBy('date_of_birth', $direction),
            'registration_date' => $query->orderBy('registration_date', $direction),
            default => $query->orderBy('created_at', $direction),
        };

        return $query->paginate($perPage);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor, Request $request): Patient
    {
        return DB::transaction(function () use ($data, $actor, $request) {
            $contacts = $data['contacts'] ?? [];
            $consent = $data['consent'] ?? null;
            $employer = $data['employer'] ?? null;
            $insurances = $data['insurances'] ?? [];

            unset($data['contacts'], $data['consent'], $data['employer'], $data['insurances']);

            $data['first_name'] = trim((string) $data['first_name']);
            $data['last_name'] = trim((string) $data['last_name']);
            if (isset($data['middle_name'])) {
                $data['middle_name'] = trim((string) $data['middle_name']) ?: null;
            }

            $data['facility_id'] = $data['facility_id'] ?? $actor->facility_id;
            $data['registration_date'] = $data['registration_date'] ?? now()->toDateString();
            $data['created_by'] = $actor->id;

            $needsAutoMrn = empty($data['mrn']);
            if ($needsAutoMrn) {
                $data['mrn'] = 'TMP-'.Str::uuid()->toString();
            }

            $patient = Patient::create($data);

            if ($needsAutoMrn) {
                $patient->forceFill([
                    'mrn' => sprintf('PAT-%06d', $patient->id),
                ])->save();
            }

            if ($consent !== null) {
                $patient->consent()->create($consent);
            }

            foreach ($contacts as $contact) {
                $patient->contacts()->create($contact);
            }

            if ($employer !== null && ! empty($employer['employer_name'])) {
                $patient->employers()->create($employer);
            }

            foreach ($insurances as $insurance) {
                $patient->insurances()->create($insurance);
            }

            $this->audit->log(
                event: 'patients.created',
                category: 'patients',
                user: $actor,
                auditable: $patient,
                success: true,
                metadata: ['patient_id' => $patient->id],
                request: $request,
            );

            return $patient->load([
                'facility',
                'primaryProvider',
                'contacts',
                'consent',
                'employers',
                'insurances',
                'issues',
                'history',
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Patient $patient, array $data, User $actor, Request $request): Patient
    {
        return DB::transaction(function () use ($patient, $data, $actor, $request) {
            $contacts = array_key_exists('contacts', $data) ? $data['contacts'] : null;
            $consent = array_key_exists('consent', $data) ? $data['consent'] : null;
            $employer = array_key_exists('employer', $data) ? $data['employer'] : null;
            $insurances = array_key_exists('insurances', $data) ? $data['insurances'] : null;

            unset($data['contacts'], $data['consent'], $data['employer'], $data['insurances']);

            if (isset($data['first_name'])) {
                $data['first_name'] = trim((string) $data['first_name']);
            }
            if (isset($data['last_name'])) {
                $data['last_name'] = trim((string) $data['last_name']);
            }
            if (array_key_exists('middle_name', $data)) {
                $data['middle_name'] = trim((string) ($data['middle_name'] ?? '')) ?: null;
            }

            $patient->fill($data);
            $dirtyFields = array_keys($patient->getDirty());
            $patient->save();

            if ($consent !== null) {
                $patient->consent()->updateOrCreate(['patient_id' => $patient->id], $consent);
                $dirtyFields[] = 'consent';
            }

            if ($contacts !== null) {
                $patient->contacts()->delete();
                foreach ($contacts as $contact) {
                    $patient->contacts()->create($contact);
                }
                $dirtyFields[] = 'contacts';
            }

            if ($employer !== null) {
                if (! empty($employer['employer_name'])) {
                    $patient->employers()->where('is_current', true)->update(['is_current' => false]);
                    $patient->employers()->create(array_merge($employer, ['is_current' => true]));
                    $dirtyFields[] = 'employer';
                }
            }

            if ($insurances !== null) {
                $patient->insurances()->delete();
                foreach ($insurances as $insurance) {
                    $patient->insurances()->create($insurance);
                }
                $dirtyFields[] = 'insurances';
            }

            $this->audit->log(
                event: 'patients.updated',
                category: 'patients',
                user: $actor,
                auditable: $patient,
                success: true,
                metadata: [
                    'patient_id' => $patient->id,
                    'changed_fields' => array_values(array_unique($dirtyFields)),
                ],
                request: $request,
            );

            return $patient->fresh([
                'facility',
                'primaryProvider',
                'contacts',
                'consent',
                'employers',
                'insurances',
                'issues',
                'history',
            ]);
        });
    }

    public function archive(Patient $patient, User $actor, Request $request): void
    {
        $patient->delete();

        $this->audit->log(
            event: 'patients.archived',
            category: 'patients',
            user: $actor,
            auditable: $patient,
            success: true,
            metadata: ['patient_id' => $patient->id],
            request: $request,
        );
    }

    public function restore(int $patientId, User $actor, Request $request): Patient
    {
        $patient = Patient::onlyTrashed()->findOrFail($patientId);
        $patient->restore();

        $this->audit->log(
            event: 'patients.restored',
            category: 'patients',
            user: $actor,
            auditable: $patient,
            success: true,
            metadata: ['patient_id' => $patient->id],
            request: $request,
        );

        return $patient->load([
            'facility',
            'primaryProvider',
            'contacts',
            'consent',
            'employers',
            'insurances',
            'issues',
            'history',
        ]);
    }

    /** @param array<string, mixed> $data */
    public function addIssue(Patient $patient, array $data, User $actor, Request $request): PatientIssue
    {
        $issue = $patient->issues()->create(array_merge($data, [
            'recorded_by' => $actor->id,
        ]));

        $this->audit->log(
            event: 'patients.issue_added',
            category: 'patients',
            user: $actor,
            auditable: $issue,
            success: true,
            metadata: ['patient_id' => $patient->id, 'issue_type' => $issue->type],
            request: $request,
        );

        return $issue;
    }

    /** @param array<string, mixed> $data */
    public function upsertHistory(Patient $patient, array $data, User $actor, Request $request): Patient
    {
        $patient->history()->updateOrCreate(
            ['patient_id' => $patient->id],
            array_merge($data, ['updated_by' => $actor->id]),
        );

        $this->audit->log(
            event: 'patients.history_updated',
            category: 'patients',
            user: $actor,
            auditable: $patient,
            success: true,
            metadata: ['patient_id' => $patient->id, 'sections' => array_keys($data)],
            request: $request,
        );

        return $patient->fresh([
            'facility',
            'primaryProvider',
            'contacts',
            'consent',
            'employers',
            'insurances',
            'issues',
            'history',
        ]);
    }
}
