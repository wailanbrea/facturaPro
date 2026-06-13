<?php

namespace Tests\Feature\Services;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_activity_logs(): void
    {
        $user = User::factory()->create();

        $log = app(ActivityLogService::class)->record(
            action: 'invoice.issued',
            properties: ['invoice_number' => 'FAC-000001'],
            user: $user,
        );

        $this->assertDatabaseHas('activity_logs', [
            'id' => $log->id,
            'user_id' => $user->id,
            'action' => 'invoice.issued',
        ]);

        $this->assertSame(1, ActivityLog::query()->count());
    }
}
