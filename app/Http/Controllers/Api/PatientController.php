<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Patients\StorePatientRequest;
use App\Http\Requests\Patients\UpdatePatientRequest;
use App\Http\Resources\PatientResource;
use App\Http\Resources\PatientSummaryResource;
use App\Models\Patient;
use App\Models\User;
use App\Services\PatientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PatientController extends Controller
{
    public function __construct(
        private readonly PatientService $patients,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $paginated = $this->patients->list($request->only([
            'search',
            'sex',
            'facility_id',
            'archived',
            'sort',
            'direction',
            'per_page',
        ]));

        return PatientSummaryResource::collection($paginated);
    }

    public function store(StorePatientRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $patient = $this->patients->create($request->validated(), $actor, $request);

        return (new PatientResource($patient))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Patient $patient): PatientResource
    {
        $patient->load([
            'facility',
            'primaryProvider',
            'contacts',
            'consent',
            'employers',
            'insurances',
            'issues',
            'history',
        ]);

        return new PatientResource($patient);
    }

    public function update(UpdatePatientRequest $request, Patient $patient): PatientResource
    {
        /** @var User $actor */
        $actor = $request->user();
        $updated = $this->patients->update($patient, $request->validated(), $actor, $request);

        return new PatientResource($updated);
    }

    public function destroy(Request $request, Patient $patient): Response
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->patients->archive($patient, $actor, $request);

        return response()->noContent();
    }

    public function restore(Request $request, int $patientId): PatientResource
    {
        /** @var User $actor */
        $actor = $request->user();
        $restored = $this->patients->restore($patientId, $actor, $request);

        return new PatientResource($restored);
    }
}
