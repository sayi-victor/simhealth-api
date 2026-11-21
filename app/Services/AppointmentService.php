<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly EncounterService $encounters,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Appointment>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 50), 1), 100);
        $search = trim((string) ($filters['search'] ?? ''));

        $query = Appointment::query()->with([
            'patient:id,mrn,title,first_name,middle_name,last_name,phone_cell',
            'provider:id,title,first_name,middle_name,last_name,suffix',
            'facility:id,name,code',
            'category:id,slug,name,color,default_duration_minutes',
            'encounter:id,encounter_number,status',
        ]);

        if (! empty($filters['date_from'])) {
            $query->whereDate('starts_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('starts_at', '<=', $filters['date_to']);
        }

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

        if ($search !== '') {
            $like = "%{$search}%";
            $query->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhere('reason', 'like', $like)
                    ->orWhereHas('patient', function ($pq) use ($like) {
                        $pq->where('mrn', 'like', $like)
                            ->orWhere('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like);
                    });
            });
        }

        return $query->orderBy('starts_at', 'asc')->paginate($perPage);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor, Request $request): Appointment
    {
        $allowOverlap = (bool) ($data['allow_overlap'] ?? false);
        unset($data['allow_overlap']);

        $category = ! empty($data['category_id'])
            ? AppointmentCategory::find($data['category_id'])
            : null;

        $startsAt = Carbon::parse($data['starts_at']);
        $duration = (int) ($data['duration_minutes'] ?? $category?->default_duration_minutes ?? 30);
        $endsAt = ! empty($data['ends_at'])
            ? Carbon::parse($data['ends_at'])
            : (clone $startsAt)->addMinutes($duration);

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => ['End time must be after start time.'],
            ]);
        }

        $providerId = $data['provider_id'] ?? null;
        if ($providerId && ! $allowOverlap) {
            $this->assertNoProviderConflict((int) $providerId, $startsAt, $endsAt);
        }

        $appointment = Appointment::create(array_merge($data, [
            'facility_id' => $data['facility_id'] ?? $actor->facility_id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'duration_minutes' => (int) $startsAt->diffInMinutes($endsAt),
            'title' => $data['title'] ?? $category?->name ?? 'Appointment',
            'status' => $data['status'] ?? 'scheduled',
            'created_by' => $actor->id,
        ]));

        $this->audit->log(
            event: 'appointments.created',
            category: 'calendar',
            user: $actor,
            auditable: $appointment,
            success: true,
            metadata: [
                'appointment_id' => $appointment->id,
                'patient_id' => $appointment->patient_id,
                'provider_id' => $appointment->provider_id,
            ],
            request: $request,
        );

        return $this->loadFull($appointment);
    }

    /** @param array<string, mixed> $data */
    public function update(Appointment $appointment, array $data, User $actor, Request $request): Appointment
    {
        $allowOverlap = (bool) ($data['allow_overlap'] ?? false);
        unset($data['allow_overlap']);

        $startsAt = isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : $appointment->starts_at;
        $duration = (int) ($data['duration_minutes'] ?? $appointment->duration_minutes);
        $endsAt = isset($data['ends_at'])
            ? Carbon::parse($data['ends_at'])
            : (isset($data['starts_at']) || isset($data['duration_minutes'])
                ? (clone $startsAt)->addMinutes($duration)
                : $appointment->ends_at);

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => ['End time must be after start time.'],
            ]);
        }

        $providerId = array_key_exists('provider_id', $data) ? $data['provider_id'] : $appointment->provider_id;
        if ($providerId && ! $allowOverlap) {
            $this->assertNoProviderConflict((int) $providerId, $startsAt, $endsAt, $appointment->id);
        }

        $appointment->fill(array_merge($data, [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'duration_minutes' => (int) $startsAt->diffInMinutes($endsAt),
        ]));
        $changedFields = array_keys($appointment->getDirty());
        $appointment->save();

        $this->audit->log(
            event: 'appointments.updated',
            category: 'calendar',
            user: $actor,
            auditable: $appointment,
            success: true,
            metadata: ['appointment_id' => $appointment->id, 'changed_fields' => $changedFields],
            request: $request,
        );

        return $this->loadFull($appointment);
    }

    /** @param array<string, mixed> $data */
    public function updateStatus(Appointment $appointment, array $data, User $actor, Request $request): Appointment
    {
        $status = $data['status'];
        $updates = ['status' => $status];

        if ($status === 'checked_in' && ! $appointment->checked_in_at) {
            $updates['checked_in_at'] = now();
        }

        if ($status === 'cancelled') {
            $updates['cancelled_at'] = now();
            if (! empty($data['cancellation_reason'])) {
                $updates['cancellation_reason'] = $data['cancellation_reason'];
            }
        }

        $appointment->forceFill($updates)->save();

        $this->audit->log(
            event: 'appointments.status_changed',
            category: 'calendar',
            user: $actor,
            auditable: $appointment,
            success: true,
            metadata: ['appointment_id' => $appointment->id, 'status' => $status],
            request: $request,
        );

        return $this->loadFull($appointment);
    }

    public function checkIn(Appointment $appointment, bool $createEncounter, User $actor, Request $request): Appointment
    {
        return DB::transaction(function () use ($appointment, $createEncounter, $actor, $request) {
            $updates = [
                'status' => 'checked_in',
                'checked_in_at' => $appointment->checked_in_at ?? now(),
            ];

            if ($createEncounter && ! $appointment->encounter_id) {
                $encounter = $this->encounters->create([
                    'patient_id' => $appointment->patient_id,
                    'facility_id' => $appointment->facility_id,
                    'provider_id' => $appointment->provider_id ?? $actor->id,
                    'encounter_date' => now(),
                    'visit_category' => 'outpatient',
                    'room_number' => $appointment->room,
                    'reason' => $appointment->reason ?: ($appointment->title ?: 'Scheduled appointment'),
                ], $actor, $request);

                $updates['encounter_id'] = $encounter->id;
            }

            $appointment->forceFill($updates)->save();

            $this->audit->log(
                event: 'appointments.checked_in',
                category: 'calendar',
                user: $actor,
                auditable: $appointment,
                success: true,
                metadata: [
                    'appointment_id' => $appointment->id,
                    'encounter_id' => $appointment->encounter_id,
                ],
                request: $request,
            );

            return $this->loadFull($appointment);
        });
    }

    public function delete(Appointment $appointment, User $actor, Request $request): void
    {
        $appointment->delete();

        $this->audit->log(
            event: 'appointments.deleted',
            category: 'calendar',
            user: $actor,
            auditable: $appointment,
            success: true,
            metadata: ['appointment_id' => $appointment->id],
            request: $request,
        );
    }

    public function loadFull(Appointment $appointment): Appointment
    {
        return $appointment->fresh([
            'patient:id,mrn,title,first_name,middle_name,last_name,phone_cell',
            'provider:id,title,first_name,middle_name,last_name,suffix',
            'facility:id,name,code',
            'category:id,slug,name,color,default_duration_minutes',
            'encounter:id,encounter_number,status',
        ]);
    }

    private function assertNoProviderConflict(int $providerId, Carbon $startsAt, Carbon $endsAt, ?int $ignoreId = null): void
    {
        $conflict = Appointment::query()
            ->where('provider_id', $providerId)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'starts_at' => ['The selected provider already has a conflicting appointment during this time slot.'],
            ]);
        }
    }
}
