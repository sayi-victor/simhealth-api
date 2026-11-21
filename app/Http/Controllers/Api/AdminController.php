<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FacilityResource;
use App\Http\Resources\UserResource;
use App\Models\Facility;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function __construct(
        private readonly AdminService $admin,
    ) {}

    public function indexUsers(Request $request): AnonymousResourceCollection
    {
        return UserResource::collection(
            $this->admin->listUsers($request->only(['search', 'role', 'is_active', 'per_page']))
        );
    }

    public function storeUser(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validate([
            'facility_id' => ['nullable', 'integer', 'exists:facilities,id'],
            'username' => ['required', 'string', 'max:100', Rule::unique('users', 'username')],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:30'],
            'department' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_provider' => ['sometimes', 'boolean'],
            'show_in_calendar' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,slug'],
            'provider_profile' => ['sometimes', 'array'],
            'provider_profile.specialty' => ['nullable', 'string', 'max:120'],
            'provider_profile.npi' => ['nullable', 'string', 'max:20'],
            'provider_profile.state_license_number' => ['nullable', 'string', 'max:50'],
        ]);

        $user = $this->admin->createUser($validated, $actor, $request);

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function updateUser(Request $request, User $user): UserResource
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validate([
            'facility_id' => ['nullable', 'integer', 'exists:facilities,id'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:30'],
            'department' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_provider' => ['sometimes', 'boolean'],
            'show_in_calendar' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,slug'],
        ]);

        return new UserResource($this->admin->updateUser($user, $validated, $actor, $request));
    }

    public function indexRoles(): JsonResponse
    {
        $roles = Role::query()->with('permissions:id,slug,module')->orderBy('name')->get()->map(fn (Role $r) => [
            'id' => $r->id,
            'slug' => $r->slug,
            'name' => $r->name,
            'description' => $r->description,
            'permissions' => $r->permissions->pluck('slug')->values()->all(),
        ]);

        return response()->json(['data' => $roles]);
    }

    public function storeFacility(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:40', Rule::unique('facilities', 'code')],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'country_code' => ['nullable', 'string', 'max:10'],
            'is_service_location' => ['sometimes', 'boolean'],
            'is_billing_location' => ['sometimes', 'boolean'],
            'is_primary' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $facility = $this->admin->createFacility($validated, $actor, $request);

        return (new FacilityResource($facility))->response()->setStatusCode(201);
    }

    public function updateFacility(Request $request, Facility $facility): FacilityResource
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:40', Rule::unique('facilities', 'code')->ignore($facility->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return new FacilityResource($this->admin->updateFacility($facility, $validated, $actor, $request));
    }

    public function indexAuditLogs(Request $request): JsonResponse
    {
        $paginated = $this->admin->listAuditLogs($request->only([
            'category',
            'event',
            'user_id',
            'success',
            'per_page',
        ]));

        return response()->json([
            'data' => $paginated->getCollection()->map(fn ($log) => [
                'id' => $log->id,
                'event' => $log->event,
                'category' => $log->category,
                'success' => $log->success,
                'ip_address' => $log->ip_address,
                'metadata' => $log->metadata,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'username' => $log->user->username,
                    'full_name' => $log->user->full_name,
                ] : null,
                'created_at' => $log->created_at?->toIso8601String(),
            ])->values()->all(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }
}
