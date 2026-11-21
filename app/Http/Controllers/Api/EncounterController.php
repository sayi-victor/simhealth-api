<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Encounters\StoreEncounterRequest;
use App\Http\Requests\Encounters\StoreNoteRequest;
use App\Http\Requests\Encounters\StoreVitalRequest;
use App\Http\Requests\Encounters\SubmitEncounterFormRequest;
use App\Http\Requests\Encounters\UpdateEncounterRequest;
use App\Http\Resources\EncounterResource;
use App\Models\Encounter;
use App\Models\FormDefinition;
use App\Models\User;
use App\Services\EncounterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EncounterController extends Controller
{
    public function __construct(
        private readonly EncounterService $encounters,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $paginated = $this->encounters->list($request->only([
            'patient_id',
            'provider_id',
            'facility_id',
            'status',
            'visit_category',
            'date_from',
            'date_to',
            'search',
            'sort',
            'direction',
            'per_page',
        ]));

        return EncounterResource::collection($paginated);
    }

    public function store(StoreEncounterRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $encounter = $this->encounters->create($request->validated(), $actor, $request);

        return (new EncounterResource($encounter))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Encounter $encounter): EncounterResource
    {
        return new EncounterResource($this->encounters->loadFull($encounter));
    }

    public function update(UpdateEncounterRequest $request, Encounter $encounter): EncounterResource
    {
        /** @var User $actor */
        $actor = $request->user();
        $updated = $this->encounters->update($encounter, $request->validated(), $actor, $request);

        return new EncounterResource($updated);
    }

    public function sign(Request $request, Encounter $encounter): EncounterResource
    {
        /** @var User $actor */
        $actor = $request->user();
        $signed = $this->encounters->sign($encounter, $actor, $request);

        return new EncounterResource($signed);
    }

    public function storeVitals(StoreVitalRequest $request, Encounter $encounter): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $vital = $this->encounters->recordVitals($encounter, $request->validated(), $actor, $request);

        return response()->json([
            'data' => [
                'id' => $vital->id,
                'recorded_at' => $vital->recorded_at?->toIso8601String(),
                'bp_systolic' => $vital->bp_systolic,
                'bp_diastolic' => $vital->bp_diastolic,
                'pulse' => $vital->pulse,
                'respiration' => $vital->respiration,
                'temperature' => $vital->temperature,
                'temp_method' => $vital->temp_method,
                'weight_kg' => $vital->weight_kg,
                'height_cm' => $vital->height_cm,
                'bmi' => $vital->bmi,
                'bmi_status' => $vital->bmi_status,
                'oxygen_saturation' => $vital->oxygen_saturation,
                'oxygen_sat_condition' => $vital->oxygen_sat_condition,
                'pain_score' => $vital->pain_score,
                'triage_level' => $vital->triage_level,
                'notes' => $vital->notes,
                'recorded_by' => $vital->recorder?->full_name,
            ],
        ], 201);
    }

    public function storeNote(StoreNoteRequest $request, Encounter $encounter): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $note = $this->encounters->addNote($encounter, $request->validated(), $actor, $request);

        return response()->json([
            'data' => [
                'id' => $note->id,
                'note_type' => $note->note_type,
                'title' => $note->title,
                'subjective' => $note->subjective,
                'objective' => $note->objective,
                'assessment' => $note->assessment,
                'plan' => $note->plan,
                'body' => $note->body,
                'is_signed' => $note->is_signed,
                'author' => $note->author?->full_name,
                'created_at' => $note->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function storeForm(SubmitEncounterFormRequest $request, Encounter $encounter): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();
        $definition = FormDefinition::with('fields')->findOrFail($validated['form_definition_id']);

        $form = $this->encounters->submitForm($encounter, $definition, $validated, $actor, $request);

        return response()->json([
            'data' => [
                'id' => $form->id,
                'form_definition_id' => $form->form_definition_id,
                'form_slug' => $form->definition?->slug,
                'form_name' => $form->definition?->name,
                'status' => $form->status,
                'data' => $form->data,
                'filled_by' => $form->filler?->full_name,
                'created_at' => $form->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
