<?php

namespace App\Services;

use App\Models\ClinicalOrder;
use App\Models\ClinicalOrderItem;
use App\Models\ClinicalOrderResult;
use App\Models\OrderCatalogItem;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ClinicalOrder>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 25), 1), 100);
        $search = trim((string) ($filters['search'] ?? ''));

        $query = ClinicalOrder::query()->with([
            'patient:id,mrn,title,first_name,middle_name,last_name',
            'provider:id,title,first_name,middle_name,last_name,suffix',
            'reviewer:id,title,first_name,middle_name,last_name,suffix',
            'items',
            'results.resulter:id,title,first_name,middle_name,last_name,suffix',
        ]);

        if (! empty($filters['patient_id'])) {
            $query->where('patient_id', (int) $filters['patient_id']);
        }

        if (! empty($filters['encounter_id'])) {
            $query->where('encounter_id', (int) $filters['encounter_id']);
        }

        if (! empty($filters['order_type'])) {
            $query->where('order_type', $filters['order_type']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if ($search !== '') {
            $like = "%{$search}%";
            $query->where(function ($q) use ($like) {
                $q->where('order_number', 'like', $like)
                    ->orWhere('specimen_number', 'like', $like)
                    ->orWhere('diagnosis', 'like', $like)
                    ->orWhereHas('patient', function ($pq) use ($like) {
                        $pq->where('mrn', 'like', $like)
                            ->orWhere('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like);
                    });
            });
        }

        return $query->orderByDesc('ordered_at')->paginate($perPage);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor, Request $request): ClinicalOrder
    {
        return DB::transaction(function () use ($data, $actor, $request) {
            $items = $data['items'];
            unset($data['items']);

            $data['provider_id'] = $data['provider_id'] ?? $actor->id;
            $data['facility_id'] = $data['facility_id'] ?? $actor->facility_id;
            $data['ordered_at'] = $data['ordered_at'] ?? now();
            $data['status'] = 'ordered';
            $data['order_number'] = 'TMP-'.Str::uuid()->toString();

            $order = ClinicalOrder::create($data);
            $order->forceFill([
                'order_number' => sprintf('ORD-%06d', $order->id),
            ])->save();

            foreach ($items as $itemData) {
                $catalog = ! empty($itemData['catalog_item_id'])
                    ? OrderCatalogItem::find($itemData['catalog_item_id'])
                    : null;

                $order->items()->create([
                    'catalog_item_id' => $catalog?->id,
                    'item_code' => $itemData['item_code'] ?? $catalog?->code ?? 'CUSTOM',
                    'item_name' => $itemData['item_name'] ?? $catalog?->name ?? 'Clinical Test',
                    'status' => 'pending',
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            $this->audit->log(
                event: 'orders.created',
                category: 'orders',
                user: $actor,
                auditable: $order,
                success: true,
                metadata: [
                    'order_id' => $order->id,
                    'order_type' => $order->order_type,
                    'item_count' => count($items),
                ],
                request: $request,
            );

            return $this->loadFull($order);
        });
    }

    /** @param array<string, mixed> $data */
    public function collectSpecimen(ClinicalOrder $order, array $data, User $actor, Request $request): ClinicalOrder
    {
        if ($order->status === 'cancelled') {
            throw ValidationException::withMessages([
                'status' => ['Cannot collect specimen for a cancelled order.'],
            ]);
        }

        $order->forceFill([
            'status' => 'collected',
            'specimen_type' => $data['specimen_type'] ?? $order->specimen_type,
            'specimen_number' => $data['specimen_number'] ?? sprintf('SPEC-%06d', $order->id),
            'collected_at' => $data['collected_at'] ?? now(),
        ])->save();

        $this->audit->log(
            event: 'orders.specimen_collected',
            category: 'orders',
            user: $actor,
            auditable: $order,
            success: true,
            metadata: ['order_id' => $order->id],
            request: $request,
        );

        return $this->loadFull($order);
    }

    /** @param array<string, mixed> $data */
    public function addResult(ClinicalOrder $order, array $data, User $actor, Request $request): ClinicalOrderResult
    {
        return DB::transaction(function () use ($order, $data, $actor, $request) {
            if ($order->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'status' => ['Cannot post results on a cancelled order.'],
                ]);
            }

            $item = null;
            if (! empty($data['clinical_order_item_id'])) {
                $item = ClinicalOrderItem::query()
                    ->where('clinical_order_id', $order->id)
                    ->findOrFail($data['clinical_order_item_id']);
            }

            $result = $order->results()->create(array_merge($data, [
                'clinical_order_item_id' => $item?->id,
                'result_code' => $data['result_code'] ?? $item?->item_code,
                'result_label' => $data['result_label'] ?? $item?->item_name ?? 'Result',
                'resulted_by' => $actor->id,
                'resulted_at' => $data['resulted_at'] ?? now(),
            ]));

            if ($item) {
                $item->forceFill(['status' => 'resulted'])->save();
            }

            $hasPending = $order->items()->where('status', 'pending')->exists();
            $order->forceFill([
                'status' => $hasPending ? 'in_progress' : 'completed',
            ])->save();

            $this->audit->log(
                event: 'orders.result_recorded',
                category: 'orders',
                user: $actor,
                auditable: $result,
                success: true,
                metadata: [
                    'order_id' => $order->id,
                    'abnormal_flag' => $result->abnormal_flag,
                ],
                request: $request,
            );

            return $result->load('resulter:id,title,first_name,middle_name,last_name,suffix');
        });
    }

    public function review(ClinicalOrder $order, User $actor, Request $request): ClinicalOrder
    {
        if (! in_array($order->status, ['completed', 'reviewed'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only completed orders can be clinically reviewed.'],
            ]);
        }

        $order->forceFill([
            'status' => 'reviewed',
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ])->save();

        $this->audit->log(
            event: 'orders.reviewed',
            category: 'orders',
            user: $actor,
            auditable: $order,
            success: true,
            metadata: ['order_id' => $order->id],
            request: $request,
        );

        return $this->loadFull($order);
    }

    public function cancel(ClinicalOrder $order, User $actor, Request $request): ClinicalOrder
    {
        if (in_array($order->status, ['completed', 'reviewed'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Completed or reviewed orders cannot be cancelled.'],
            ]);
        }

        $order->forceFill(['status' => 'cancelled'])->save();
        $order->items()->where('status', 'pending')->update(['status' => 'cancelled']);

        $this->audit->log(
            event: 'orders.cancelled',
            category: 'orders',
            user: $actor,
            auditable: $order,
            success: true,
            metadata: ['order_id' => $order->id],
            request: $request,
        );

        return $this->loadFull($order);
    }

    public function loadFull(ClinicalOrder $order): ClinicalOrder
    {
        return $order->fresh([
            'patient:id,mrn,title,first_name,middle_name,last_name',
            'provider:id,title,first_name,middle_name,last_name,suffix',
            'reviewer:id,title,first_name,middle_name,last_name,suffix',
            'items.catalogItem',
            'results.resulter:id,title,first_name,middle_name,last_name,suffix',
        ]);
    }
}
