<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Calendar\StoreAppointmentRequest;
use App\Http\Requests\Calendar\UpdateAppointmentRequest;
use App\Http\Requests\Calendar\UpdateAppointmentStatusRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\AppointmentCategory;
use App\Models\User;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointments,
    ) {}

    public function categories(): JsonResponse
    {
        $categories = AppointmentCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'slug', 'name', 'color', 'default_duration_minutes', 'provider_mandatory']);

        return response()->json(['data' => $categories]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $paginated = $this->appointments->list($request->only([
            'date_from',
            'date_to',
            'patient_id',
            'provider_id',
            'facility_id',
            'status',
            'search',
            'per_page',
        ]));

        return AppointmentResource::collection($paginated);
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $appointment = $this->appointments->create($request->validated(), $actor, $request);

        return (new AppointmentResource($appointment))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Appointment $appointment): AppointmentResource
    {
        return new AppointmentResource($this->appointments->loadFull($appointment));
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment): AppointmentResource
    {
        /** @var User $actor */
        $actor = $request->user();
        $updated = $this->appointments->update($appointment, $request->validated(), $actor, $request);

        return new AppointmentResource($updated);
    }

    public function updateStatus(UpdateAppointmentStatusRequest $request, Appointment $appointment): AppointmentResource
    {
        /** @var User $actor */
        $actor = $request->user();
        $updated = $this->appointments->updateStatus($appointment, $request->validated(), $actor, $request);

        return new AppointmentResource($updated);
    }

    public function checkIn(Request $request, Appointment $appointment): AppointmentResource
    {
        /** @var User $actor */
        $actor = $request->user();
        $createEncounter = $request->boolean('create_encounter', false);
        $updated = $this->appointments->checkIn($appointment, $createEncounter, $actor, $request);

        return new AppointmentResource($updated);
    }

    public function destroy(Request $request, Appointment $appointment): Response
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->appointments->delete($appointment, $actor, $request);

        return response()->noContent();
    }
}
