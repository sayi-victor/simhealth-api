<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\StoreOrderRequest;
use App\Http\Requests\Orders\StoreOrderResultRequest;
use App\Http\Resources\ClinicalOrderResource;
use App\Models\ClinicalOrder;
use App\Models\OrderCatalogItem;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
    ) {}

    public function catalog(Request $request): JsonResponse
    {
        $query = OrderCatalogItem::query()->where('is_active', true);

        if ($type = $request->query('order_type')) {
            $query->where('order_type', $type);
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $like = "%{$search}%";
            $query->where(fn ($q) => $q->where('code', 'like', $like)->orWhere('name', 'like', $like));
        }

        return response()->json([
            'data' => $query->orderBy('order_type')->orderBy('name')->get(),
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return ClinicalOrderResource::collection(
            $this->orders->list($request->only([
                'patient_id',
                'encounter_id',
                'order_type',
                'priority',
                'status',
                'search',
                'per_page',
            ]))
        );
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $order = $this->orders->create($request->validated(), $actor, $request);

        return (new ClinicalOrderResource($order))->response()->setStatusCode(201);
    }

    public function show(ClinicalOrder $order): ClinicalOrderResource
    {
        return new ClinicalOrderResource($this->orders->loadFull($order));
    }

    public function collect(Request $request, ClinicalOrder $order): ClinicalOrderResource
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validate([
            'specimen_type' => ['nullable', 'string', 'max:60'],
            'specimen_number' => ['nullable', 'string', 'max:60'],
            'collected_at' => ['nullable', 'date'],
        ]);

        return new ClinicalOrderResource($this->orders->collectSpecimen($order, $validated, $actor, $request));
    }

    public function storeResult(StoreOrderResultRequest $request, ClinicalOrder $order): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $result = $this->orders->addResult($order, $request->validated(), $actor, $request);

        return response()->json([
            'data' => [
                'id' => $result->id,
                'clinical_order_id' => $result->clinical_order_id,
                'clinical_order_item_id' => $result->clinical_order_item_id,
                'result_code' => $result->result_code,
                'result_label' => $result->result_label,
                'value' => $result->value,
                'units' => $result->units,
                'reference_range' => $result->reference_range,
                'abnormal_flag' => $result->abnormal_flag,
                'findings' => $result->findings,
                'impression' => $result->impression,
                'result_status' => $result->result_status,
                'resulted_by' => $result->resulter?->full_name,
                'resulted_at' => $result->resulted_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function review(Request $request, ClinicalOrder $order): ClinicalOrderResource
    {
        /** @var User $actor */
        $actor = $request->user();

        return new ClinicalOrderResource($this->orders->review($order, $actor, $request));
    }

    public function cancel(Request $request, ClinicalOrder $order): ClinicalOrderResource
    {
        /** @var User $actor */
        $actor = $request->user();

        return new ClinicalOrderResource($this->orders->cancel($order, $actor, $request));
    }
}
