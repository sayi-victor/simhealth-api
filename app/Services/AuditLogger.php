<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    /**
     * Record an audit event. Never pass PHI (names, DOB, MRN, clinical notes) in $metadata (R13).
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function log(
        string $event,
        string $category,
        ?User $user = null,
        ?Model $auditable = null,
        bool $success = true,
        ?array $metadata = null,
        ?Request $request = null,
    ): AuditLog {
        $req = $request ?? request();

        return AuditLog::create([
            'user_id' => $user?->id,
            'event' => $event,
            'category' => $category,
            'auditable_type' => $auditable ? $auditable->getMorphClass() : null,
            'auditable_id' => $auditable?->getKey(),
            'ip_address' => $req?->ip(),
            'user_agent' => $req ? mb_substr((string) $req->userAgent(), 0, 500) : null,
            'success' => $success,
            'metadata' => $metadata,
        ]);
    }
}
