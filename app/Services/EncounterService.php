<?php

namespace App\Services;

use App\Models\Encounter;
use App\Models\EncounterForm;
use App\Models\EncounterNote;
use App\Models\EncounterVital;
use App\Models\FormDefinition;
use App\Models\FormField;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EncounterService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Encounter>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 25), 1), 100);
        $search = trim((string) ($filters['search'] ?? ''));
        $sort = in_array($filters['sort'] ?? '', ['encounter_date', 'encounter_number', 'status', 'created_at'], true)
            ? (string) $filters['sort']
            : 'encounter_date';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query = Encounter::query()->with([
            'patient:id,mrn,title,first_name,middle_name,last_name,date_of_birth,sex',
            'facility:id,name,code',
            'provider:id,title,first_name,middle_name,last_name,suffix',
        ]);

        if (! empty($filters['patient_id'])) {
            $query->where('patient_id', (int) $filters['patient_id']);
        }

        if (! empty($filters['provider_id'])) {
            $query->where('provider_id', (int) $filters['provider_id']);
        }

        if (! empty($filters['facility_id'])) {
            $query->where('facility_id', (int) $filters['facility_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['visit_category'])) {
            $query->where('visit_category', $filters['visit_category']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('encounter_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('encounter_date', '<=', $filters['date_to']);
        }

        if ($search !== '') {
            $like = "%{$search}%";
            $query->where(function ($q) use ($like) {
                $q->where('encounter_number', 'like', $like)
                    ->orWhere('reason', 'like', $like)
                    ->orWhereHas('patient', function ($pq) use ($like) {
                        $pq->where('mrn', 'like', $like)
                            ->orWhere('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like);
                    });
            });
        }

        return $query->orderBy($sort, $direction)->paginate($perPage);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor, Request $request): Encounter
    {
        return DB::transaction(function () use ($data, $actor, $request) {
            $data['facility_id'] = $data['facility_id'] ?? $actor->facility_id;
            $data['provider_id'] = $data['provider_id'] ?? $actor->id;
            $data['encounter_date'] = $data['encounter_date'] ?? now();
            $data['status'] = $data['status'] ?? 'open';
            $data['created_by'] = $actor->id;
            $data['encounter_number'] = 'TMP-'.Str::uuid()->toString();

            $encounter = Encounter::create($data);

            $encounter->forceFill([
                'encounter_number' => sprintf('ENC-%06d', $encounter->id),
            ])->save();

            $this->audit->log(
                event: 'encounters.created',
                category: 'encounters',
                user: $actor,
                auditable: $encounter,
                success: true,
                metadata: [
                    'encounter_id' => $encounter->id,
                    'patient_id' => $encounter->patient_id,
                ],
                request: $request,
            );

            return $this->loadFull($encounter);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Encounter $encounter, array $data, User $actor, Request $request): Encounter
    {
        if ($encounter->status === 'signed') {
            throw ValidationException::withMessages([
                'status' => ['Signed encounters are locked and cannot be modified.'],
            ]);
        }

        $encounter->fill($data);
        $changedFields = array_keys($encounter->getDirty());
        $encounter->save();

        $this->audit->log(
            event: 'encounters.updated',
            category: 'encounters',
            user: $actor,
            auditable: $encounter,
            success: true,
            metadata: [
                'encounter_id' => $encounter->id,
                'changed_fields' => $changedFields,
            ],
            request: $request,
        );

        return $this->loadFull($encounter);
    }

    public function sign(Encounter $encounter, User $actor, Request $request): Encounter
    {
        if ($encounter->status === 'signed') {
            return $this->loadFull($encounter);
        }

        $encounter->forceFill([
            'status' => 'signed',
            'signed_by' => $actor->id,
            'signed_at' => now(),
        ])->save();

        $this->audit->log(
            event: 'encounters.signed',
            category: 'encounters',
            user: $actor,
            auditable: $encounter,
            success: true,
            metadata: ['encounter_id' => $encounter->id],
            request: $request,
        );

        return $this->loadFull($encounter);
    }

    /** @param array<string, mixed> $data */
    public function recordVitals(Encounter $encounter, array $data, User $actor, Request $request): EncounterVital
    {
        $weight = isset($data['weight_kg']) ? (float) $data['weight_kg'] : null;
        $height = isset($data['height_cm']) ? (float) $data['height_cm'] : null;

        if ($weight && $height && $height > 0) {
            $heightM = $height / 100;
            $bmi = round($weight / ($heightM * $heightM), 1);
            $data['bmi'] = $bmi;
            $data['bmi_status'] = match (true) {
                $bmi < 18.5 => 'Underweight',
                $bmi < 25.0 => 'Normal',
                $bmi < 30.0 => 'Overweight',
                default => 'Obese',
            };
        }

        $vital = $encounter->vitals()->create(array_merge($data, [
            'patient_id' => $encounter->patient_id,
            'recorded_at' => $data['recorded_at'] ?? now(),
            'recorded_by' => $actor->id,
        ]));

        $this->audit->log(
            event: 'encounters.vitals_recorded',
            category: 'encounters',
            user: $actor,
            auditable: $vital,
            success: true,
            metadata: ['encounter_id' => $encounter->id],
            request: $request,
        );

        return $vital->load('recorder:id,title,first_name,middle_name,last_name,suffix');
    }

    /** @param array<string, mixed> $data */
    public function addNote(Encounter $encounter, array $data, User $actor, Request $request): EncounterNote
    {
        $note = $encounter->notes()->create(array_merge($data, [
            'patient_id' => $encounter->patient_id,
            'authored_by' => $actor->id,
        ]));

        $this->audit->log(
            event: 'encounters.note_added',
            category: 'encounters',
            user: $actor,
            auditable: $note,
            success: true,
            metadata: ['encounter_id' => $encounter->id, 'note_type' => $note->note_type],
            request: $request,
        );

        return $note->load('author:id,title,first_name,middle_name,last_name,suffix');
    }

    /** @param array<string, mixed> $payload */
    public function submitForm(Encounter $encounter, FormDefinition $definition, array $payload, User $actor, Request $request): EncounterForm
    {
        $definition->loadMissing('fields');

        $rules = [];
        foreach ($definition->fields as $field) {
            $rules["data.{$field->field_key}"] = $this->rulesForField($field);
        }

        $validator = Validator::make(['data' => $payload['data'] ?? []], $rules);
        $validated = $validator->validate();

        $allowedKeys = $definition->fields->pluck('field_key')->all();
        $cleanData = array_intersect_key($validated['data'] ?? [], array_flip($allowedKeys));

        $form = $encounter->forms()->create([
            'patient_id' => $encounter->patient_id,
            'form_definition_id' => $definition->id,
            'status' => $payload['status'] ?? 'completed',
            'data' => $cleanData,
            'filled_by' => $actor->id,
        ]);

        $this->audit->log(
            event: 'encounters.form_submitted',
            category: 'encounters',
            user: $actor,
            auditable: $form,
            success: true,
            metadata: ['encounter_id' => $encounter->id, 'form_slug' => $definition->slug],
            request: $request,
        );

        return $form->load([
            'definition.fields',
            'filler:id,title,first_name,middle_name,last_name,suffix',
        ]);
    }

    public function loadFull(Encounter $encounter): Encounter
    {
        return $encounter->fresh([
            'patient:id,mrn,title,first_name,middle_name,last_name,date_of_birth,sex,clinical_alert',
            'facility:id,name,code',
            'provider:id,title,first_name,middle_name,last_name,suffix',
            'signer:id,title,first_name,middle_name,last_name,suffix',
            'vitals.recorder:id,title,first_name,middle_name,last_name,suffix',
            'notes.author:id,title,first_name,middle_name,last_name,suffix',
            'forms.definition.fields',
            'forms.filler:id,title,first_name,middle_name,last_name,suffix',
        ]);
    }

    /** @return array<int, mixed> */
    private function rulesForField(FormField $field): array
    {
        $rules = [$field->is_required ? 'required' : 'nullable'];

        match ($field->field_type) {
            'number' => $rules[] = 'numeric',
            'date', 'datetime' => $rules[] = 'date',
            'checkbox' => $rules[] = 'boolean',
            'select', 'radio' => is_array($field->options) && count($field->options) > 0
                ? $rules[] = Rule::in($field->options)
                : $rules[] = 'string',
            default => $rules[] = 'string',
        };

        return $rules;
    }
}
