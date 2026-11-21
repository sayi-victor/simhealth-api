<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\StoreDrugLotRequest;
use App\Http\Requests\Pharmacy\StoreDrugRequest;
use App\Http\Resources\DrugResource;
use App\Models\Drug;
use App\Models\User;
use App\Services\PharmacyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DrugController extends Controller
{
    public function __construct(
        private readonly PharmacyService $pharmacy,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return DrugResource::collection(
            $this->pharmacy->listDrugs($request->only(['search', 'is_active', 'per_page']))
        );
    }

    public function store(StoreDrugRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $drug = $this->pharmacy->createDrug($request->validated(), $actor, $request);

        return (new DrugResource($drug))->response()->setStatusCode(201);
    }

    public function show(Drug $drug): DrugResource
    {
        $drug->load('lots');

        return new DrugResource($drug);
    }

    public function storeLot(StoreDrugLotRequest $request, Drug $drug): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $lot = $this->pharmacy->addLot($drug, $request->validated(), $actor, $request);

        return response()->json([
            'data' => [
                'id' => $lot->id,
                'drug_id' => $lot->drug_id,
                'lot_number' => $lot->lot_number,
                'quantity_on_hand' => $lot->quantity_on_hand,
                'unit_cost' => $lot->unit_cost,
                'expires_at' => $lot->expires_at?->toDateString(),
            ],
        ], 201);
    }
}
