<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Facility;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function listUsers(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 25), 1), 100);
        $search = trim((string) ($filters['search'] ?? ''));

        $query = User::query()->with(['facility:id,name,code', 'roles:id,slug,name']);

        if ($search !== '') {
            $like = "%{$search}%";
            $query->where(function ($q) use ($like) {
                $q->where('username', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        if (! empty($filters['role'])) {
            $roleSlug = $filters['role'];
            $query->whereHas('roles', fn ($rq) => $rq->where('slug', $roleSlug));
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderBy('last_name')->orderBy('first_name')->paginate($perPage);
    }

    /** @param array<string, mixed> $data */
    public function createUser(array $data, User $actor, Request $request): User
    {
        return DB::transaction(function () use ($data, $actor, $request) {
            $roleSlugs = $data['roles'] ?? [];
            $providerProfile = $data['provider_profile'] ?? null;
            unset($data['roles'], $data['provider_profile']);

            $user = User::create($data);

            if (! empty($roleSlugs)) {
                $roleIds = Role::whereIn('slug', $roleSlugs)->pluck('id')->all();
                $user->roles()->sync($roleIds);
            }

            if ($providerProfile !== null) {
                $user->providerProfile()->create($providerProfile);
            }

            $this->audit->log(
                event: 'admin.user_created',
                category: 'admin',
                user: $actor,
                auditable: $user,
                success: true,
                metadata: ['created_user_id' => $user->id, 'roles' => $roleSlugs],
                request: $request,
            );

            return $user->fresh(['facility', 'roles.permissions']);
        });
    }

    /** @param array<string, mixed> $data */
    public function updateUser(User $user, array $data, User $actor, Request $request): User
    {
        return DB::transaction(function () use ($user, $data, $actor, $request) {
            $roleSlugs = array_key_exists('roles', $data) ? $data['roles'] : null;
            unset($data['roles']);

            if (empty($data['password'])) {
                unset($data['password']);
            }

            $user->fill($data);
            $changed = array_keys($user->getDirty());
            $user->save();

            if ($roleSlugs !== null) {
                $roleIds = Role::whereIn('slug', $roleSlugs)->pluck('id')->all();
                $user->roles()->sync($roleIds);
                $changed[] = 'roles';
            }

            $this->audit->log(
                event: 'admin.user_updated',
                category: 'admin',
                user: $actor,
                auditable: $user,
                success: true,
                metadata: ['target_user_id' => $user->id, 'changed_fields' => array_values(array_unique($changed))],
                request: $request,
            );

            return $user->fresh(['facility', 'roles.permissions']);
        });
    }

    /** @param array<string, mixed> $data */
    public function createFacility(array $data, User $actor, Request $request): Facility
    {
        $facility = Facility::create($data);

        $this->audit->log(
            event: 'admin.facility_created',
            category: 'admin',
            user: $actor,
            auditable: $facility,
            success: true,
            metadata: ['facility_id' => $facility->id],
            request: $request,
        );

        return $facility;
    }

    /** @param array<string, mixed> $data */
    public function updateFacility(Facility $facility, array $data, User $actor, Request $request): Facility
    {
        $facility->fill($data);
        $changed = array_keys($facility->getDirty());
        $facility->save();

        $this->audit->log(
            event: 'admin.facility_updated',
            category: 'admin',
            user: $actor,
            auditable: $facility,
            success: true,
            metadata: ['facility_id' => $facility->id, 'changed_fields' => $changed],
            request: $request,
        );

        return $facility->fresh();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, AuditLog>
     */
    public function listAuditLogs(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 50), 1), 100);

        $query = AuditLog::query()->with(['user:id,username,first_name,last_name']);

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (! empty($filters['event'])) {
            $query->where('event', 'like', '%'.$filters['event'].'%');
        }
        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }
        if (isset($filters['success'])) {
            $query->where('success', filter_var($filters['success'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage);
    }
}
