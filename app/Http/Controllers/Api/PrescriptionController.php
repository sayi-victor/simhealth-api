<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\DispensePrescriptionRequest;
use App\Http\Requests\Pharmacy\StorePrescriptionRequest;
use App\Http\Resources\PrescriptionResource;
use App\Models\Prescription;
use App\Models\User;
use App\Services\PharmacyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PrescriptionController extends Controller
{
    public function __construct(
        private readonly PharmacyService $pharmacy,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return PrescriptionResource::collection(
            $this->pharmacy->listPrescriptions($request->only([
                'patient_id',
                'encounter_id',
                'status',
                'search',
                'per_page',
            ]))
        );
    }

    public function store(StorePrescriptionRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $rx = $this->pharmacy->createPrescription($request->validated(), $actor, $request);

        return (new PrescriptionResource($rx))->response()->setStatusCode(201);
    }

    public function show(Prescription $prescription): PrescriptionResource
    {
        $prescription->load([
            'patient:id,mrn,title,first_name,middle_name,last_name',
            'provider:id,title,first_name,middle_name,last_name,suffix',
            'drug:id,code,name,strength,form,unit_price',
            'dispensations.lot:id,lot_number,expires_at',
            'dispensations.dispenser:id,title,first_name,middle_name,last_name,suffix',
        ]);

        return new PrescriptionResource($prescription);
    }

    public function dispense(DispensePrescriptionRequest $request, Prescription $prescription): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $dispensation = $this->pharmacy->dispense($prescription, $request->validated(), $actor, $request);

        return response()->json([
            'data' => [
                'id' => $dispensation->id,
                'prescription_id' => $dispensation->prescription_id,
                'drug_lot_id' => $dispensation->drug_lot_id,
                'lot_number' => $dispensation->lot?->lot_number,
                'quantity' => $dispensation->quantity,
                'unit_price' => $dispensation->unit_price,
                'total_price' => $dispensation->total_price,
                'dispensed_by' => $dispensation->dispenser?->full_name,
                'dispensed_at' => $dispensation->dispensed_at?->toIso8601String(),
                'notes' => $dispensation->notes,
            ],
        ], 201);
    }

    public function cancel(Request $request, Prescription $prescription): PrescriptionResource
    {
        /** @var User $actor */
        $actor = $request->user();
        $cancelled = $this->pharmacy->cancelPrescription($prescription, $actor, $request);

        return new PrescriptionResource($cancelled);
    }
}
