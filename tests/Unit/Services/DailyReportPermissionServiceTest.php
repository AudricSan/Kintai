<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Services\DailyReportPermissionService;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;

final class DailyReportPermissionServiceTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Service dont l'utilisateur détient les clés données, portée sur $storeId (ou globale si null). */
    private function serviceGranting(array $keys, ?int $storeId = null): DailyReportPermissionService
    {
        $assignments = $this->createStub(RoleAssignmentRepositoryInterface::class);
        $assignments->method('findByUser')->willReturn([
            ['id' => 1, 'user_id' => 10, 'role_id' => 1, 'scope_type' => $storeId === null ? 'global' : 'store', 'scope_id' => $storeId],
        ]);
        $roles = $this->createStub(RoleRepositoryInterface::class);
        $roles->method('findById')->willReturn(['id' => 1, 'is_system' => 0]);
        $roles->method('getPermissions')->willReturn($keys);

        return new DailyReportPermissionService(new PermissionService($assignments, $roles));
    }

    /** Service pour un utilisateur sans aucune permission (libre-service uniquement). */
    private function serviceWithoutPermissions(): DailyReportPermissionService
    {
        $assignments = $this->createStub(RoleAssignmentRepositoryInterface::class);
        $assignments->method('findByUser')->willReturn([]);
        $roles = $this->createStub(RoleRepositoryInterface::class);

        return new DailyReportPermissionService(new PermissionService($assignments, $roles));
    }

    private function store(array $settings = []): array
    {
        $json = $settings ? json_encode($settings) : null;
        return ['id' => 1, 'name' => 'Test Store', 'daily_report_settings' => $json];
    }

    private function user(int $id = 99): array
    {
        return ['id' => $id];
    }

    private function membership(): array
    {
        return ['role' => 'employee', 'store_id' => 1];
    }

    private function report(string $status = 'draft', int $authorId = 99): array
    {
        return ['id' => 5, 'store_id' => 1, 'status' => $status, 'author_id' => $authorId, 'deleted_at' => null];
    }

    // -------------------------------------------------------------------------
    // getSettings / isEnabled
    // -------------------------------------------------------------------------

    public function testGetSettingsReturnsDefaultsWhenNoJson(): void
    {
        $service  = $this->serviceWithoutPermissions();
        $settings = $service->getSettings(['id' => 1, 'daily_report_settings' => null]);

        $this->assertTrue($settings['enabled']);
        $this->assertEmpty($settings['mail_recipients']);
        $this->assertFalse($settings['auto_send_on_validate']);
    }

    public function testGetSettingsMergesCustomValues(): void
    {
        $service  = $this->serviceWithoutPermissions();
        $settings = $service->getSettings($this->store(['enabled' => false, 'mail_recipients' => ['a@example.com']]));

        $this->assertFalse($settings['enabled']);
        $this->assertSame(['a@example.com'], $settings['mail_recipients']);
    }

    // -------------------------------------------------------------------------
    // canCreate
    // -------------------------------------------------------------------------

    public function testPermissionHolderCanAlwaysCreate(): void
    {
        $service = $this->serviceGranting(['daily_reports.create'], storeId: 1);
        $this->assertTrue($service->canCreate($this->user(10), $this->store(), null));
    }

    public function testStoreMemberCanCreateAsSelfServiceWhenEnabled(): void
    {
        $service = $this->serviceWithoutPermissions();
        $this->assertTrue($service->canCreate($this->user(), $this->store(['enabled' => true]), $this->membership()));
    }

    public function testCannotCreateWithoutMembershipOrPermission(): void
    {
        $service = $this->serviceWithoutPermissions();
        $this->assertFalse($service->canCreate($this->user(), $this->store(), null));
    }

    public function testCannotCreateWhenDisabledEvenAsMember(): void
    {
        $service = $this->serviceWithoutPermissions();
        $this->assertFalse($service->canCreate($this->user(), $this->store(['enabled' => false]), $this->membership()));
    }

    public function testPermissionHolderCannotCreateWhenDisabled(): void
    {
        $service = $this->serviceGranting(['daily_reports.create'], storeId: 1);
        $this->assertFalse($service->canCreate($this->user(10), $this->store(['enabled' => false]), null));
    }

    // -------------------------------------------------------------------------
    // canEdit
    // -------------------------------------------------------------------------

    public function testAuthorCanEditOwnDraftAsSelfService(): void
    {
        $service = $this->serviceWithoutPermissions();
        $this->assertTrue($service->canEdit($this->user(10), $this->store(), $this->report('draft', 10), $this->membership()));
    }

    public function testPermissionHolderCanEditAnyDraft(): void
    {
        $service = $this->serviceGranting(['daily_reports.update'], storeId: 1);
        $this->assertTrue($service->canEdit($this->user(10), $this->store(), $this->report('draft', 99), $this->membership()));
    }

    public function testPermissionHolderCanEditSubmittedReport(): void
    {
        $service = $this->serviceGranting(['daily_reports.update'], storeId: 1);
        $this->assertTrue($service->canEdit($this->user(10), $this->store(), $this->report('submitted', 99), $this->membership()));
    }

    public function testAuthorCannotEditOwnSubmittedReportWithoutPermission(): void
    {
        $service = $this->serviceWithoutPermissions();
        $this->assertFalse($service->canEdit($this->user(10), $this->store(), $this->report('submitted', 10), $this->membership()));
    }

    public function testNonAuthorCannotEditOthersDraftWithoutPermission(): void
    {
        $service = $this->serviceWithoutPermissions();
        $this->assertFalse($service->canEdit($this->user(20), $this->store(), $this->report('draft', 10), $this->membership()));
    }

    public function testCannotEditValidatedReportEvenWithPermission(): void
    {
        $service = $this->serviceGranting(['daily_reports.update'], storeId: 1);
        $this->assertFalse($service->canEdit($this->user(10), $this->store(), $this->report('validated', 10), $this->membership()));
    }

    // -------------------------------------------------------------------------
    // canSubmit
    // -------------------------------------------------------------------------

    public function testAuthorCanSubmitOwnDraftAsSelfService(): void
    {
        $service = $this->serviceWithoutPermissions();
        $this->assertTrue($service->canSubmit($this->user(10), $this->store(), $this->report('draft', 10), $this->membership()));
    }

    public function testCannotSubmitOthersDraftWithoutPermission(): void
    {
        $service = $this->serviceWithoutPermissions();
        $this->assertFalse($service->canSubmit($this->user(20), $this->store(), $this->report('draft', 10), $this->membership()));
    }

    public function testPermissionHolderCanSubmitAnyDraft(): void
    {
        $service = $this->serviceGranting(['daily_reports.submit'], storeId: 1);
        $this->assertTrue($service->canSubmit($this->user(20), $this->store(), $this->report('draft', 10), $this->membership()));
    }

    public function testCannotSubmitAlreadySubmittedReport(): void
    {
        $service = $this->serviceWithoutPermissions();
        $this->assertFalse($service->canSubmit($this->user(10), $this->store(), $this->report('submitted', 10), $this->membership()));
    }

    // -------------------------------------------------------------------------
    // canValidate
    // -------------------------------------------------------------------------

    public function testPermissionHolderCanValidateSubmittedReport(): void
    {
        $service = $this->serviceGranting(['daily_reports.approve'], storeId: 1);
        $this->assertTrue($service->canValidate($this->user(10), $this->store(), $this->report('submitted'), $this->membership()));
    }

    public function testAuthorCannotValidateOwnReportWithoutPermission(): void
    {
        $service = $this->serviceWithoutPermissions();
        $this->assertFalse($service->canValidate($this->user(99), $this->store(), $this->report('submitted', 99), $this->membership()));
    }

    public function testCannotValidateDraftReport(): void
    {
        $service = $this->serviceGranting(['daily_reports.approve'], storeId: 1);
        $this->assertFalse($service->canValidate($this->user(10), $this->store(), $this->report('draft'), $this->membership()));
    }

    // -------------------------------------------------------------------------
    // canSendMail
    // -------------------------------------------------------------------------

    public function testPermissionHolderCanSendMailForValidatedReport(): void
    {
        $service = $this->serviceGranting(['daily_reports.approve'], storeId: 1);
        $this->assertTrue($service->canSendMail($this->user(10), $this->store(), $this->report('validated'), $this->membership()));
    }

    public function testNonPermissionHolderCannotSendMail(): void
    {
        $service = $this->serviceWithoutPermissions();
        $this->assertFalse($service->canSendMail($this->user(99), $this->store(), $this->report('validated', 99), $this->membership()));
    }

    public function testCannotSendMailForNonValidatedReport(): void
    {
        $service = $this->serviceGranting(['daily_reports.approve'], storeId: 1);
        $this->assertFalse($service->canSendMail($this->user(10), $this->store(), $this->report('submitted'), $this->membership()));
    }

    // -------------------------------------------------------------------------
    // canDelete
    // -------------------------------------------------------------------------

    public function testPermissionHolderCanDeleteReport(): void
    {
        $service = $this->serviceGranting(['daily_reports.delete'], storeId: 1);
        $this->assertTrue($service->canDelete($this->user(10), $this->store(), $this->report(), $this->membership()));
    }

    public function testAuthorCannotDeleteOwnReportWithoutPermission(): void
    {
        $service = $this->serviceWithoutPermissions();
        $this->assertFalse($service->canDelete($this->user(99), $this->store(), $this->report(status: 'draft', authorId: 99), $this->membership()));
    }

    public function testCannotDeleteAlreadyDeletedReport(): void
    {
        $service = $this->serviceGranting(['daily_reports.delete'], storeId: 1);
        $report  = array_merge($this->report(), ['deleted_at' => '2025-01-01 00:00:00']);
        $this->assertFalse($service->canDelete($this->user(10), $this->store(), $report, null));
    }

    // -------------------------------------------------------------------------
    // canViewList
    // -------------------------------------------------------------------------

    public function testPermissionHolderCanViewListEvenWhenDisabled(): void
    {
        $service = $this->serviceGranting(['daily_reports.view'], storeId: 1);
        $this->assertTrue($service->canViewList($this->user(10), $this->store(['enabled' => false]), null));
    }

    public function testMemberCanViewListAsSelfServiceWhenEnabled(): void
    {
        $service = $this->serviceWithoutPermissions();
        $this->assertTrue($service->canViewList($this->user(), $this->store(['enabled' => true]), $this->membership()));
    }

    public function testMemberCannotViewListWhenDisabled(): void
    {
        $service = $this->serviceWithoutPermissions();
        $this->assertFalse($service->canViewList($this->user(), $this->store(['enabled' => false]), $this->membership()));
    }

    public function testNonMemberCannotViewList(): void
    {
        $service = $this->serviceWithoutPermissions();
        $this->assertFalse($service->canViewList($this->user(), $this->store(), null));
    }

    // -------------------------------------------------------------------------
    // canViewReport
    // -------------------------------------------------------------------------

    public function testAuthorCanViewOwnReportAsSelfService(): void
    {
        $service = $this->serviceWithoutPermissions();
        $this->assertTrue($service->canViewReport($this->user(10), $this->store(), $this->report('draft', 10), $this->membership()));
    }

    public function testNonAuthorCannotViewOthersReportWithoutPermission(): void
    {
        $service = $this->serviceWithoutPermissions();
        $this->assertFalse($service->canViewReport($this->user(20), $this->store(), $this->report('draft', 10), $this->membership()));
    }

    public function testPermissionHolderCanViewAnyReport(): void
    {
        $service = $this->serviceGranting(['daily_reports.view'], storeId: 1);
        $this->assertTrue($service->canViewReport($this->user(10), $this->store(), $this->report('draft', 99), $this->membership()));
    }

    public function testNonMemberCannotViewReport(): void
    {
        $service = $this->serviceWithoutPermissions();
        $this->assertFalse($service->canViewReport($this->user(20), $this->store(), $this->report('draft', 10), null));
    }

    // -------------------------------------------------------------------------
    // canManageSettings
    // -------------------------------------------------------------------------

    public function testPermissionHolderCanManageSettings(): void
    {
        $service = $this->serviceGranting(['daily_reports.update'], storeId: 1);
        $this->assertTrue($service->canManageSettings($this->user(10), $this->store()));
    }

    public function testNonPermissionHolderCannotManageSettings(): void
    {
        $service = $this->serviceWithoutPermissions();
        $this->assertFalse($service->canManageSettings($this->user(99), $this->store()));
    }
}
