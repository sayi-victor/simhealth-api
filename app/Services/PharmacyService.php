<?php

namespace App\Services;

use App\Models\Drug;
use App\Models\DrugLot;
use App\Models\Prescription;
use App\Models\PrescriptionDispensation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PharmacyService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Drug>
     */
    public function listDrugs(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 25), 1), 100);
        $search = trim((string) ($filters['search'] ?? ''));

        $query = Drug::query()->with(['lots' => function ($q) {
            $q->where('is_active', true)->where('quantity_on_hand', '>', 0)->orderBy('expires_at');
        }]);

        if ($search !== '') {
            $like = "%{$search}%";
            $query->where(function ($q) use ($like) {
                $q->where('code', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('generic_name', 'like', $like);
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    /** @param array<string, mixed> $data */
    public function createDrug(array $data, User $actor, Request $request): Drug
    {
        $drug = Drug::create($data);

        $this->audit->log(
            event: 'pharmacy.drug_created',
            category: 'pharmacy',
            user: $actor,
            auditable: $drug,
            success: true,
            metadata: ['drug_id' => $drug->id, 'code' => $drug->code],
            request: $request,
        );

        return $drug->load('lots');
    }

    /** @param array<string, mixed> $data */
    public function addLot(Drug $drug, array $data, User $actor, Request $request): DrugLot
    {
        $lot = $drug->lots()->create(array_merge($data, [
            'facility_id' => $data['facility_id'] ?? $actor->facility_id,
            'received_at' => $data['received_at'] ?? now()->toDateString(),
        ]));

        $this->audit->log(
            event: 'pharmacy.lot_received',
            category: 'pharmacy',
            user: $actor,
            auditable: $lot,
            success: true,
            metadata: ['drug_id' => $drug->id, 'lot_id' => $lot->id, 'quantity' => $lot->quantity_on_hand],
            request: $request,
        );

        return $lot;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Prescription>
     */
    public function listPrescriptions(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 25), 1), 100);
        $search = trim((string) ($filters['search'] ?? ''));

        $query = Prescription::query()->with([
            'patient:id,mrn,title,first_name,middle_name,last_name',
            'provider:id,title,first_name,middle_name,last_name,suffix',
            'drug:id,code,name,strength,form,unit_price',
            'dispensations.lot:id,lot_number,expires_at',
            'dispensations.dispenser:id,title,first_name,middle_name,last_name,suffix',
        ]);

        if (! empty($filters['patient_id'])) {
            $query->where('patient_id', (int) $filters['patient_id']);
        }

        if (! empty($filters['encounter_id'])) {
            $query->where('encounter_id', (int) $filters['encounter_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if ($search !== '') {
            $like = "%{$search}%";
            $query->where(function ($q) use ($like) {
                $q->where('prescription_number', 'like', $like)
                    ->orWhere('drug_name', 'like', $like)
                    ->orWhereHas('patient', function ($pq) use ($like) {
                        $pq->where('mrn', 'like', $like)
                            ->orWhere('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like);
                    });
            });
        }

        return $query->orderByDesc('prescribed_at')->paginate($perPage);
    }

    /** @param array<string, mixed> $data */
    public function createPrescription(array $data, User $actor, Request $request): Prescription
    {
        return DB::transaction(function () use ($data, $actor, $request) {
            $drug = ! empty($data['drug_id']) ? Drug::find($data['drug_id']) : null;

            $data['drug_name'] = $data['drug_name'] ?? $drug?->name ?? 'Medication';
            $data['route'] = $data['route'] ?? $drug?->route;
            $data['provider_id'] = $data['provider_id'] ?? $actor->id;
            $data['facility_id'] = $data['facility_id'] ?? $actor->facility_id;
            $data['prescribed_at'] = $data['prescribed_at'] ?? now();
            $data['status'] = 'pending';
            $data['prescription_number'] = 'TMP-'.Str::uuid()->toString();

            $rx = Prescription::create($data);
            $rx->forceFill([
                'prescription_number' => sprintf('RX-%06d', $rx->id),
            ])->save();

            $this->audit->log(
                event: 'prescriptions.created',
                category: 'pharmacy',
                user: $actor,
                auditable: $rx,
                success: true,
                metadata: [
                    'prescription_id' => $rx->id,
                    'patient_id' => $rx->patient_id,
                    'drug_id' => $rx->drug_id,
                ],
                request: $request,
            );

            return $rx->fresh([
                'patient:id,mrn,title,first_name,middle_name,last_name',
                'provider:id,title,first_name,middle_name,last_name,suffix',
                'drug:id,code,name,strength,form,unit_price',
                'dispensations',
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    public function dispense(Prescription $prescription, array $data, User $actor, Request $request): PrescriptionDispensation
    {
        return DB::transaction(function () use ($prescription, $data, $actor, $request) {
            $rx = Prescription::query()->lockForUpdate()->findOrFail($prescription->id);

            if ($rx->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'prescription' => ['Cancelled prescriptions cannot be dispensed.'],
                ]);
            }

            $remaining = $rx->quantity_prescribed - $rx->quantity_dispensed;
            $qty = (int) $data['quantity'];

            if ($remaining <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['This prescription has already been fully dispensed.'],
                ]);
            }

            if ($qty > $remaining) {
                throw ValidationException::withMessages([
                    'quantity' => ["Cannot dispense {$qty}; only {$remaining} remaining on this prescription."],
                ]);
            }

            if (! $rx->drug_id) {
                throw ValidationException::withMessages([
                    'drug_id' => ['External non-formulary prescriptions cannot be dispensed from in-house stock.'],
                ]);
            }

            $lotQuery = DrugLot::query()
                ->where('drug_id', $rx->drug_id)
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', now()->toDateString());
                })
                ->lockForUpdate();

            if (! empty($data['drug_lot_id'])) {
                $lot = $lotQuery->where('id', (int) $data['drug_lot_id'])->first();
            } else {
                // FEFO: earliest expiring non-expired lot with enough stock
                $lot = $lotQuery
                    ->where('quantity_on_hand', '>=', $qty)
                    ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END, expires_at ASC')
                    ->first();
            }

            if (! $lot) {
                throw ValidationException::withMessages([
                    'drug_lot_id' => ['No active, non-expired stock lot is available with sufficient quantity.'],
                ]);
            }

            if ($lot->quantity_on_hand < $qty) {
                throw ValidationException::withMessages([
                    'quantity' => ["Insufficient stock in lot {$lot->lot_number} ({$lot->quantity_on_hand} on hand)."],
                ]);
            }

            $drug = Drug::findOrFail($rx->drug_id);
            $unitPrice = isset($data['unit_price']) ? (string) $data['unit_price'] : (string) $drug->unit_price;
            $totalPrice = number_format(((float) $unitPrice) * $qty, 2, '.', '');

            $lot->decrement('quantity_on_hand', $qty);

            $newDispensed = $rx->quantity_dispensed + $qty;
            $rx->forceFill([
                'quantity_dispensed' => $newDispensed,
                'status' => $newDispensed >= $rx->quantity_prescribed ? 'dispensed' : 'partially_dispensed',
            ])->save();

            $dispensation = PrescriptionDispensation::create([
                'prescription_id' => $rx->id,
                'patient_id' => $rx->patient_id,
                'drug_id' => $rx->drug_id,
                'drug_lot_id' => $lot->id,
                'dispensed_by' => $actor->id,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
                'dispensed_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->audit->log(
                event: 'pharmacy.dispensed',
                category: 'pharmacy',
                user: $actor,
                auditable: $dispensation,
                success: true,
                metadata: [
                    'prescription_id' => $rx->id,
                    'drug_lot_id' => $lot->id,
                    'quantity' => $qty,
                ],
                request: $request,
            );

            return $dispensation->load([
                'lot:id,lot_number,expires_at',
                'dispenser:id,title,first_name,middle_name,last_name,suffix',
            ]);
        });
    }

    public function cancelPrescription(Prescription $prescription, User $actor, Request $request): Prescription
    {
        if ($prescription->status === 'dispensed') {
            throw ValidationException::withMessages([
                'status' => ['Fully dispensed prescriptions cannot be cancelled.'],
            ]);
        }

        $prescription->forceFill(['status' => 'cancelled'])->save();

        $this->audit->log(
            event: 'prescriptions.cancelled',
            category: 'pharmacy',
            user: $actor,
            auditable: $prescription,
            success: true,
            metadata: ['prescription_id' => $prescription->id],
            request: $request,
        );

        return $prescription->fresh(['patient', 'provider', 'drug', 'dispensations']);
    }
}
