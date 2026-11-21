<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdmissionResource;
use App\Http\Resources\ImmunizationResource;
use App\Http\Resources\SurgeryResource;
use App\Models\Admission;
use App\Models\Surgery;
use App\Models\User;
use App\Models\Ward;
use App\Services\ClinicalModulesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ClinicalModulesController extends Controller
{
    public function __construct(
        private readonly ClinicalModulesService $clinical,
    ) {}

    public function indexWards(): JsonResponse
    {
        $wards = Ward::query()
            ->with('beds')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $wards]);
    }

    public function indexAdmissions(Request $request): AnonymousResourceCollection
    {
        return AdmissionResource::collection(
            $this->clinical->listAdmissions($request->only(['status', 'ward_id', 'patient_id', 'per_page']))
        );
    }

    public function storeAdmission(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'encounter_id' => ['nullable', 'integer', 'exists:encounters,id'],
            'bed_id' => ['required', 'integer', 'exists:beds,id'],
            'admission_type' => ['sometimes', Rule::in(['elective', 'emergency', 'maternity', 'transfer', 'observation'])],
            'expected_discharge_date' => ['nullable', 'date'],
            'admission_diagnosis' => ['nullable', 'string', 'max:255'],
            'admission_notes' => ['nullable', 'string'],
        ]);

        $admission = $this->clinical->admit($validated, $actor, $request);

        return (new AdmissionResource($admission))->response()->setStatusCode(201);
    }

    public function transferAdmission(Request $request, Admission $admission): AdmissionResource
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validate([
            'bed_id' => ['required', 'integer', 'exists:beds,id'],
        ]);

        return new AdmissionResource($this->clinical->transferBed($admission, $validated, $actor, $request));
    }

    public function dischargeAdmission(Request $request, Admission $admission): AdmissionResource
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validate([
            'discharge_diagnosis' => ['nullable', 'string', 'max:255'],
            'discharge_summary' => ['nullable', 'string'],
            'discharge_disposition' => ['sometimes', Rule::in(['home', 'transferred', 'against_medical_advice', 'deceased'])],
        ]);

        return new AdmissionResource($this->clinical->discharge($admission, $validated, $actor, $request));
    }

    public function indexSurgeries(Request $request): AnonymousResourceCollection
    {
        return SurgeryResource::collection(
            $this->clinical->listSurgeries($request->only(['status', 'patient_id', 'per_page']))
        );
    }

    public function storeSurgery(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'encounter_id' => ['nullable', 'integer', 'exists:encounters,id'],
            'admission_id' => ['nullable', 'integer', 'exists:admissions,id'],
            'surgeon_id' => ['nullable', 'integer', 'exists:users,id'],
            'anesthetist_id' => ['nullable', 'integer', 'exists:users,id'],
            'procedure_name' => ['required', 'string', 'max:200'],
            'procedure_code' => ['nullable', 'string', 'max:60'],
            'theater_room' => ['nullable', 'string', 'max:60'],
            'priority' => ['sometimes', Rule::in(['elective', 'urgent', 'emergency'])],
            'scheduled_at' => ['required', 'date'],
            'anesthesia_type' => ['nullable', Rule::in(['general', 'regional', 'local', 'sedation'])],
            'consent_signed' => ['sometimes', 'boolean'],
            'pre_op_diagnosis' => ['nullable', 'string', 'max:255'],
        ]);

        $surgery = $this->clinical->scheduleSurgery($validated, $actor, $request);

        return (new SurgeryResource($surgery))->response()->setStatusCode(201);
    }

    public function completeSurgery(Request $request, Surgery $surgery): SurgeryResource
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validate([
            'post_op_diagnosis' => ['nullable', 'string', 'max:255'],
            'operative_notes' => ['nullable', 'string'],
            'complications' => ['nullable', 'string'],
        ]);

        return new SurgeryResource($this->clinical->completeSurgery($surgery, $validated, $actor, $request));
    }

    public function indexImmunizations(Request $request): AnonymousResourceCollection
    {
        return ImmunizationResource::collection(
            $this->clinical->listImmunizations($request->only(['patient_id', 'vaccine_name', 'per_page']))
        );
    }

    public function storeImmunization(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'encounter_id' => ['nullable', 'integer', 'exists:encounters,id'],
            'vaccine_name' => ['required', 'string', 'max:150'],
            'cvx_code' => ['nullable', 'string', 'max:20'],
            'dose_number' => ['nullable', 'integer', 'between:1,10'],
            'dose_amount' => ['nullable', 'numeric', 'min:0'],
            'dose_unit' => ['nullable', 'string', 'max:30'],
            'route' => ['nullable', Rule::in(['im', 'sc', 'oral', 'intradermal', 'intranasal'])],
            'site' => ['nullable', 'string', 'max:60'],
            'manufacturer' => ['nullable', 'string', 'max:120'],
            'lot_number' => ['nullable', 'string', 'max:60'],
            'expires_at' => ['nullable', 'date'],
            'administered_at' => ['nullable', 'date'],
            'next_due_date' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(['completed', 'refused', 'not_administered'])],
            'notes' => ['nullable', 'string'],
        ]);

        $imm = $this->clinical->recordImmunization($validated, $actor, $request);

        return (new ImmunizationResource($imm))->response()->setStatusCode(201);
    }
}
