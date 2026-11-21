<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Patients\StorePatientIssueRequest;
use App\Http\Resources\PatientIssueResource;
use App\Models\Patient;
use App\Models\PatientIssue;
use App\Models\User;
use App\Services\PatientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PatientIssueController extends Controller
{
    public function __construct(
        private readonly PatientService $patients,
    ) {}

    public function store(StorePatientIssueRequest $request, Patient $patient): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $issue = $this->patients->addIssue($patient, $request->validated(), $actor, $request);

        return (new PatientIssueResource($issue))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, Patient $patient, PatientIssue $issue): Response
    {
        if ($issue->patient_id !== $patient->id) {
            abort(404);
        }

        $issue->delete();

        return response()->noContent();
    }
}
