<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use kintai\Core\Services\DailyReportPermissionService;

final class DailyReportPermissionServiceTest extends TestCase
{
    private DailyReportPermissionService $service;

    protected function setUp(): void
    {
        $this->service = new DailyReportPermissionService();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function store(array $settings = []): array
    {
        $json = $settings ? json_encode($settings) : null;
        return ['id' => 1, 'name' => 'Test Store', 'daily_report_settings' => $json];
    }

    private function user(bool $isAdmin = false, int $id = 10): array
    {
        return ['id' => $id, 'is_admin' => $isAdmin ? 1 : 0];
    }

    private function membership(string $role): array
    {
        return ['role' => $role, 'store_id' => 1];
    }

    private function report(string $status = 'draft', int $authorId = 10): array
    {
        return ['id' => 5, 'store_id' => 1, 'status' => $status, 'author_id' => $authorId, 'deleted_at' => null];
    }

    // -------------------------------------------------------------------------
    // getSettings
    // -------------------------------------------------------------------------

    public function testGetSettingsReturnsDefaultsWhenNoJson(): void
    {
        $store    = ['id' => 1, 'daily_report_settings' => null];
        $settings = $this->service->getSettings($store);

        $this->assertTrue($settings['enabled']);
        $this->assertContains('staff', $settings['can_submit_roles']);
        $this->assertContains('manager', $settings['can_validate_roles']);
        $this->assertEmpty($settings['mail_recipients']);
        $this->assertFalse($settings['auto_send_on_validate']);
    }

    public function testGetSettingsMergesCustomValues(): void
    {
        $store    = $this->store(['enabled' => false, 'can_validate_roles' => ['admin']]);
        $settings = $this->service->getSettings($store);

        $this->assertFalse($settings['enabled']);
        $this->assertSame(['admin'], $settings['can_validate_roles']);
        // Non-overridden defaults preserved
        $this->assertContains('staff', $settings['can_submit_roles']);
    }

    // -------------------------------------------------------------------------
    // canCreate
    // -------------------------------------------------------------------------

    public function testGlobalAdminCanAlwaysCreate(): void
    {
        $this->assertTrue($this->service->canCreate($this->user(true), $this->store(), null));
    }

    public function testStaffMemberCanCreateWhenEnabled(): void
    {
        $this->assertTrue(
            $this->service->canCreate($this->user(), $this->store(['enabled' => true]), $this->membership('staff'))
        );
    }

    public function testCannotCreateWithoutMembership(): void
    {
        $this->assertFalse($this->service->canCreate($this->user(), $this->store(), null));
    }

    public function testCannotCreateWhenDisabled(): void
    {
        $this->assertFalse(
            $this->service->canCreate($this->user(), $this->store(['enabled' => false]), $this->membership('staff'))
        );
    }

    // -------------------------------------------------------------------------
    // canEdit
    // -------------------------------------------------------------------------

    public function testAuthorCanEditOwnDraft(): void
    {
        $this->assertTrue(
            $this->service->canEdit($this->user(false, 10), $this->store(), $this->report('draft', 10), $this->membership('staff'))
        );
    }

    public function testManagerCanEditAnyDraft(): void
    {
        $this->assertTrue(
            $this->service->canEdit($this->user(false, 99), $this->store(), $this->report('draft', 10), $this->membership('manager'))
        );
    }

    public function testStaffCannotEditOwnSubmittedReport(): void
    {
        $this->assertFalse(
            $this->service->canEdit($this->user(false, 10), $this->store(), $this->report('submitted', 10), $this->membership('staff'))
        );
    }

    public function testManagerCanEditSubmittedReport(): void
    {
        $this->assertTrue(
            $this->service->canEdit($this->user(false, 99), $this->store(), $this->report('submitted', 10), $this->membership('manager'))
        );
    }

    public function testAdminCanEditSubmittedReport(): void
    {
        $this->assertTrue(
            $this->service->canEdit($this->user(false, 99), $this->store(), $this->report('submitted', 10), $this->membership('admin'))
        );
    }

    public function testGlobalAdminCanEditSubmittedReport(): void
    {
        $this->assertTrue(
            $this->service->canEdit($this->user(true), $this->store(), $this->report('submitted', 10), null)
        );
    }

    public function testCannotEditValidatedReport(): void
    {
        $this->assertFalse(
            $this->service->canEdit($this->user(false, 99), $this->store(), $this->report('validated', 10), $this->membership('manager'))
        );
    }

    public function testNonAuthorStaffCannotEditOthersDraft(): void
    {
        $this->assertFalse(
            $this->service->canEdit($this->user(false, 20), $this->store(), $this->report('draft', 10), $this->membership('staff'))
        );
    }

    // -------------------------------------------------------------------------
    // canSubmit
    // -------------------------------------------------------------------------

    public function testStaffCanSubmitDraftByDefault(): void
    {
        $this->assertTrue(
            $this->service->canSubmit($this->user(), $this->store(), $this->report('draft'), $this->membership('staff'))
        );
    }

    public function testCannotSubmitAlreadySubmittedReport(): void
    {
        $this->assertFalse(
            $this->service->canSubmit($this->user(), $this->store(), $this->report('submitted'), $this->membership('staff'))
        );
    }

    public function testCannotSubmitWhenRoleNotAllowed(): void
    {
        $store = $this->store(['can_submit_roles' => ['manager', 'admin']]);
        $this->assertFalse(
            $this->service->canSubmit($this->user(), $store, $this->report('draft'), $this->membership('staff'))
        );
    }

    // -------------------------------------------------------------------------
    // canValidate
    // -------------------------------------------------------------------------

    public function testManagerCanValidateSubmittedReport(): void
    {
        $this->assertTrue(
            $this->service->canValidate($this->user(), $this->store(), $this->report('submitted'), $this->membership('manager'))
        );
    }

    public function testStaffCannotValidateByDefault(): void
    {
        $this->assertFalse(
            $this->service->canValidate($this->user(), $this->store(), $this->report('submitted'), $this->membership('staff'))
        );
    }

    public function testCannotValidateDraftReport(): void
    {
        $this->assertFalse(
            $this->service->canValidate($this->user(), $this->store(), $this->report('draft'), $this->membership('manager'))
        );
    }

    // -------------------------------------------------------------------------
    // canSendMail
    // -------------------------------------------------------------------------

    public function testManagerCanSendMailForValidatedReport(): void
    {
        $this->assertTrue(
            $this->service->canSendMail($this->user(), $this->store(), $this->report('validated'), $this->membership('manager'))
        );
    }

    public function testStaffCannotSendMail(): void
    {
        $this->assertFalse(
            $this->service->canSendMail($this->user(), $this->store(), $this->report('validated'), $this->membership('staff'))
        );
    }

    public function testCannotSendMailForNonValidatedReport(): void
    {
        $this->assertFalse(
            $this->service->canSendMail($this->user(), $this->store(), $this->report('submitted'), $this->membership('manager'))
        );
    }

    // -------------------------------------------------------------------------
    // canDelete
    // -------------------------------------------------------------------------

    public function testManagerCanDeleteReport(): void
    {
        $this->assertTrue(
            $this->service->canDelete($this->user(), $this->store(), $this->report(), $this->membership('manager'))
        );
    }

    public function testStaffCannotDeleteReport(): void
    {
        $this->assertFalse(
            $this->service->canDelete($this->user(), $this->store(), $this->report(), $this->membership('staff'))
        );
    }

    public function testCannotDeleteAlreadyDeletedReport(): void
    {
        $report = array_merge($this->report(), ['deleted_at' => '2025-01-01 00:00:00']);
        $this->assertFalse(
            $this->service->canDelete($this->user(true), $this->store(), $report, null)
        );
    }

    // -------------------------------------------------------------------------
    // canViewReport
    // -------------------------------------------------------------------------

    public function testStaffCanViewOwnReport(): void
    {
        $this->assertTrue(
            $this->service->canViewReport($this->user(false, 10), $this->store(), $this->report('draft', 10), $this->membership('staff'))
        );
    }

    public function testStaffCannotViewOthersReport(): void
    {
        $this->assertFalse(
            $this->service->canViewReport($this->user(false, 20), $this->store(), $this->report('draft', 10), $this->membership('staff'))
        );
    }

    public function testManagerCanViewAnyReport(): void
    {
        $this->assertTrue(
            $this->service->canViewReport($this->user(false, 99), $this->store(), $this->report('draft', 10), $this->membership('manager'))
        );
    }
}
