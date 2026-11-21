<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Patients\UpdatePatientHistoryRequest;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use App\Models\User;
use App\Services\PatientService;

class PatientHistoryController extends Controller
{
    public function __construct(
        private readonly PatientService $patients,
    ) {}

    public function update(UpdatePatientHistoryRequest $request, Patient $patient): PatientResource
    {
        /** @var User $actor */
        $actor = $request->user();
        $updated = $this->patients->upsertHistory($patient, $request->validated(), $actor, $request);

        return new PatientResource($updated);
    }
}
