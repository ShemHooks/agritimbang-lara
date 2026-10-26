<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class AuditLogTest extends AnalyticsTestCase
{
    private function entry($actor, $municipality = null, string $action = 'price.approved'): AuditLog
    {
        return app(AuditLogService::class)->record(
            $actor, $action, $actor, $municipality?->id,
            ['status' => 'pending_review'], ['status' => 'official']
        );
    }

    public function test_guest_cannot_view_history(): void
    {
        $this->getJson('/api/audit-logs')->assertUnauthorized();
    }

    public function test_farmer_cannot_view_history(): void
    {
        $this->authenticate('farmer');
        $this->getJson('/api/audit-logs')->assertForbidden();
    }

    public function test_encoder_cannot_view_history(): void
    {
        $this->authenticate('lgu_encoder', $this->municipality());
        $this->getJson('/api/audit-logs')->assertForbidden();
    }

    public function test_admin_sees_history_from_all_municipalities(): void
    {
        $first = $this->municipality();
        $second = $this->municipality();
        $actor = $this->user('lgu_encoder', $first);
        $this->entry($actor, $first);
        $this->entry($actor, $second);
        $this->authenticate('admin');
        $this->getJson('/api/audit-logs')->assertOk()->assertJsonCount(2, 'data.data');
    }

    public function test_lgu_authority_sees_only_own_municipality(): void
    {
        $first = $this->municipality();
        $second = $this->municipality();
        $actor = $this->user('lgu_encoder', $first);
        $this->entry($actor, $first);
        $this->entry($actor, $second);
        $this->entry($actor); // Global entries are not visible to LGU.
        $this->authenticate('lgu_authority', $first);
        $this->getJson('/api/audit-logs')->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_lgu_cannot_request_other_municipality(): void
    {
        $own = $this->municipality();
        $other = $this->municipality();
        $this->authenticate('lgu_authority', $own);
        $this->getJson('/api/audit-logs?municipality_id='.$other->id)->assertForbidden();
    }

    public function test_lgu_without_active_assignment_is_denied(): void
    {
        $this->authenticate('lgu_authority', $this->municipality(false));
        $this->getJson('/api/audit-logs')->assertForbidden();
    }

    public function test_sensitive_fields_are_redacted_recursively(): void
    {
        $actor = $this->user('admin');
        $log = app(AuditLogService::class)->record($actor, 'user.updated', $actor, null,
            ['password' => 'secret', 'profile' => ['otp_code' => '123456', 'name' => 'Before']],
            ['access_token' => 'secret', 'profile' => ['name' => 'After', 'document_path' => '/secret']]
        );
        $this->assertSame(['profile' => ['name' => 'Before']], $log->old_values);
        $this->assertSame(['profile' => ['name' => 'After']], $log->new_values);
    }

    public function test_audit_record_rolls_back_with_business_transaction(): void
    {
        $actor = $this->user('admin');
        try {
            DB::transaction(function () use ($actor) {
                $this->entry($actor);
                throw new \RuntimeException('Operation failed');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('Operation failed', $exception->getMessage());
        }
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_action_filter_and_pagination(): void
    {
        $actor = $this->user('admin');
        $this->entry($actor, null, 'price.approved');
        $this->entry($actor, null, 'transaction.verified');
        $this->authenticate('admin');
        $this->getJson('/api/audit-logs?action=price.approved&per_page=1')
            ->assertOk()->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.total', 1);
    }
}
