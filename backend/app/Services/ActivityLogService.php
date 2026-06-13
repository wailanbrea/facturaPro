<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityLogService
{
    /**
     * @param array<string, mixed> $properties
     */
    public function record(
        string $action,
        ?Model $subject = null,
        array $properties = [],
        ?User $user = null,
        ?Request $request = null,
    ): ActivityLog {
        return ActivityLog::query()->create([
            'user_id' => $user?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
