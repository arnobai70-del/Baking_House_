<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_logger_records_actor_subject_and_redacts_secrets(): void
    {
        $actor = User::factory()->create();
        $subject = User::factory()->create();
        $this->actingAs($actor);

        $log = app(AuditLogger::class)->record(
            'integration.updated',
            $subject,
            ['api_key' => 'old-secret', 'mode' => 'sandbox'],
            ['api_key' => 'new-secret', 'mode' => 'live'],
            'Go-live',
        );

        $this->assertSame($actor->id, $log->actor_id);
        $this->assertSame($subject->id, $log->subject_id);
        $this->assertSame(['api_key' => '[redacted]', 'mode' => 'live'], $log->fresh()->after);
        $this->assertStringNotContainsString('secret', (string) json_encode($log->fresh()->before));
    }

    public function test_audit_logs_cannot_be_updated(): void
    {
        $log = app(AuditLogger::class)->record('test.action');

        $this->expectException(LogicException::class);
        $log->update(['action' => 'tampered']);
    }

    public function test_audit_logs_cannot_be_deleted(): void
    {
        $log = app(AuditLogger::class)->record('test.action');

        $this->expectException(LogicException::class);
        $log->delete();
    }

    public function test_audit_page_requires_audit_permission(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/audit')->assertForbidden();
        $this->assertSame(0, AuditLog::query()->count());
    }
}
