<?php

declare(strict_types=1);

use kintai\UI\Controller\Web\AuthController;
use kintai\UI\Controller\Web\PasswordResetController;
use kintai\UI\Controller\Web\NotificationController;
use kintai\UI\Controller\Web\CronController;
use kintai\UI\Controller\Web\EmployeeController;
use kintai\UI\Controller\Web\HomeController;
use kintai\UI\Controller\Web\IcalController;
use kintai\UI\Controller\Web\DocsController;
use kintai\UI\Controller\Web\PrivacyController;
use kintai\UI\Controller\Web\PwaController;
use kintai\UI\Controller\Web\StorageFileController;

use kintai\UI\Controller\Web\Requests\AdminRequestsController;
use kintai\UI\Controller\Web\Scheduling\AdminShiftController;
use kintai\UI\Controller\Web\Scheduling\AdminShiftImportController;
use kintai\UI\Controller\Web\Scheduling\AdminShiftTypeController;

use kintai\UI\Controller\Web\Staff\AdminStoreController;
use kintai\UI\Controller\Web\Staff\AdminUserController;

use kintai\UI\Controller\Web\System\ActivityController;
use kintai\UI\Controller\Web\System\AdminController;
use kintai\UI\Controller\Web\System\AdminRoleController;
use kintai\UI\Controller\Web\System\AppResetController;
use kintai\UI\Controller\Web\System\BackupController;
use kintai\UI\Controller\Web\System\BundleSettingsController;
use kintai\UI\Controller\Web\System\LanguageController;
use kintai\UI\Controller\Web\System\MailTestController;
use kintai\UI\Controller\Web\System\OwnerSettingsController;
use kintai\Core\Middleware\AuthMiddleware;
use kintai\Core\Middleware\PermissionMiddleware;
use kintai\Core\Middleware\ApiAuthMiddleware;
use kintai\Core\Middleware\ApiPermissionMiddleware;
use kintai\Core\Middleware\RateLimiterMiddleware;
use kintai\UI\Controller\Api\V1\AuthController as ApiAuthController;
use kintai\UI\Controller\Api\V1\UserController as ApiUserController;
use kintai\UI\Controller\Api\V1\StoreController as ApiStoreController;
use kintai\UI\Controller\Api\V1\StoreUserController as ApiStoreUserController;
use kintai\UI\Controller\Api\V1\ShiftTypeController as ApiShiftTypeController;
use kintai\UI\Controller\Api\V1\ShiftController as ApiShiftController;
use kintai\UI\Controller\Api\V1\AvailabilityController as ApiAvailabilityController;
use kintai\UI\Controller\Api\V1\NotificationController as ApiNotificationController;
use kintai\UI\Controller\Api\V1\UserShiftRateController as ApiUserShiftRateController;
use kintai\UI\Controller\Api\V1\ActivityController as ApiActivityController;
use kintai\UI\Controller\Api\V1\IcalTokenController as ApiIcalTokenController;
use kintai\UI\Controller\Api\V1\UserPrefsController as ApiUserPrefsController;


/** @var \kintai\Core\Router $router */

// =============================================================================
// Routes publiques (sans authentification)
// =============================================================================

// --- CRON ---
$router->get('/cron/auto-validate', [CronController::class, 'autoValidate'], name: 'cron.auto_validate');
$router->get('/cron/run/{job}', [CronController::class, 'run'], name: 'cron.run');

// --- iCal ---
$router->get('/ical/{token}/{store_id}/shifts.ics', [IcalController::class, 'feed'], name: 'ical.feed');

// --- Authentification ---
$router->get('/login',  [AuthController::class, 'showLogin'], name: 'auth.login');
$router->post('/login', [AuthController::class, 'login'], middleware: [RateLimiterMiddleware::class], name: 'auth.login.post');
$router->post('/logout', [AuthController::class, 'logout'], name: 'auth.logout');

// --- Réinitialisation de mot de passe ---
$router->get('/forgot-password',  [PasswordResetController::class, 'showForgotForm'], name: 'password.forgot');
$router->post('/forgot-password', [PasswordResetController::class, 'sendLink'], middleware: [RateLimiterMiddleware::class], name: 'password.forgot.post');
$router->get('/reset-password/{token}',  [PasswordResetController::class, 'showResetForm'], name: 'password.reset');
$router->post('/reset-password/{token}', [PasswordResetController::class, 'reset'], name: 'password.reset.post');

// --- PWA ---
$router->get('/manifest.json', [PwaController::class, 'manifest'], name: 'pwa.manifest');

// --- Confidentialité ---
$router->get('/privacy', [PrivacyController::class, 'show'], name: 'privacy');

// --- Fichiers uploadés (photos de stores, imports) — réservé aux admins/managers ---
$router->get('/storage/{path*}', [StorageFileController::class, 'serve'], middleware: [AuthMiddleware::class, PermissionMiddleware::class], name: 'storage.file', permission: 'public');

// =============================================================================
// Routes authentifiées (tout utilisateur connecté)
// =============================================================================

$router->group('/profile', function ($r) {
    $r->get('',              [AuthController::class, 'showProfile'],          name: 'profile');
    $r->post('',             [AuthController::class, 'updateProfile'],        name: 'profile.post');
    $r->post('/password',    [AuthController::class, 'saveProfilePassword'],  name: 'profile.password');
    $r->get('/export',       [AuthController::class, 'exportData'],           name: 'profile.export');
    $r->post('/delete',      [AuthController::class, 'deleteAccount'],        name: 'profile.delete');
}, middleware: [AuthMiddleware::class]);

$router->group('/notifications', function ($r) {
    $r->get('',              [NotificationController::class, 'index'],       name: 'notifications.index');
    $r->get('/poll',         [NotificationController::class, 'poll'],        name: 'notifications.poll');
    $r->post('/read-all',    [NotificationController::class, 'markAllRead'], name: 'notifications.read_all');
    $r->post('/{id}/read',   [NotificationController::class, 'markRead'],    name: 'notifications.read');
}, middleware: [AuthMiddleware::class]);

// --- Documentation ---
$router->get('/docs', [DocsController::class, 'index'], middleware: [AuthMiddleware::class], name: 'docs.index');
$router->post('/docs/sync', [DocsController::class, 'sync'], middleware: [AuthMiddleware::class], name: 'docs.sync');
$router->get('/docs/{lang}/{page}', [DocsController::class, 'show'], middleware: [AuthMiddleware::class], name: 'docs.show');

$router->group('/employee', function ($r) {

    // Dashboard
    $r->get('',             [EmployeeController::class, 'dashboard'],    name: 'employee.dashboard');
    $r->post('/dashboard/widgets', [EmployeeController::class, 'saveDashboardWidgets'], name: 'employee.dashboard.widgets');

    // Shifts
    $r->get('/shifts',          [EmployeeController::class, 'shifts'],         name: 'employee.shifts');
    $r->get('/shifts/calendar', [EmployeeController::class, 'shiftsCalendar'], name: 'employee.shifts.calendar');
    $r->get('/shifts/day',      [EmployeeController::class, 'shiftDay'],       name: 'employee.shifts.day');
    $r->get('/shifts/week',     [EmployeeController::class, 'shiftsWeek'],     name: 'employee.shifts.week');

    // Pointage : voir src/Bundles/Timeclock/routes.php

    // Congés : voir src/Bundles/TimeOff/routes.php

    // Profil : géré par /profile (AuthController, page unique admin/manager/employé)
    $r->get('/profile',                    [EmployeeController::class, 'profile'],               name: 'employee.profile');
    $r->post('/profile/availability',      [EmployeeController::class, 'saveProfileAvailability'], name: 'employee.profile.availability');

    // Navigation
    $r->get('/nav-settings',  [EmployeeController::class, 'navSettings'],     name: 'employee.nav_settings');
    $r->post('/nav-settings', [EmployeeController::class, 'saveNavSettings'], name: 'employee.nav_settings.save');

    // Feedback : voir src/Bundles/Feedback/routes.php

    // Échanges de shifts : voir src/Bundles/ShiftSwap/routes.php

    // Bourse aux shifts : voir src/Bundles/ShiftClaim/routes.php

    // Messagerie : voir src/Bundles/Messaging/routes.php (employee.messages*)

}, middleware: [AuthMiddleware::class]);

// --- Changement de vue / langue ---
$router->post('/switch-view',   [AuthController::class, 'switchView'],   middleware: [AuthMiddleware::class], name: 'switch.view');
$router->post('/switch-device', [AuthController::class, 'switchDevice'], middleware: [AuthMiddleware::class], name: 'switch.device');
$router->get('/lang/{locale}', [AuthController::class, 'switchLanguage'], name: 'lang.switch');

// --- Accueil / Dashboard ---
$router->get('/', [HomeController::class, 'index'], middleware: [AuthMiddleware::class, PermissionMiddleware::class], name: 'home', permission: 'public');
$router->post('/admin/dashboard/widgets', [HomeController::class, 'saveDashboardWidgets'], middleware: [AuthMiddleware::class, PermissionMiddleware::class], name: 'admin.dashboard.widgets', permission: 'public');

// =============================================================================
// Routes administration (web)
// =============================================================================

$router->group('/admin', function ($r) {

    // Configuration organisation
    $r->get('/owner-settings',  [OwnerSettingsController::class, 'show'], name: 'admin.owner_settings', permission: 'public');
    $r->post('/owner-settings', [OwnerSettingsController::class, 'save'], name: 'admin.owner_settings.save', permission: 'public');

    // Navigation
    $r->get('/nav-settings',  [AdminController::class, 'navSettings'],     name: 'admin.nav_settings', permission: 'public');
    $r->post('/nav-settings', [AdminController::class, 'saveNavSettings'], name: 'admin.nav_settings.save', permission: 'public');

    // Demandes (résumé) : agrège congés, échanges de shifts et bourse aux shifts
    $r->get('/requests', [AdminRequestsController::class, 'index'], name: 'admin.requests', permission: 'public');

    // Utilisateurs
    $r->get('/users',                     [AdminUserController::class, 'users'],               name: 'admin.users', permission: 'employees.view');
    $r->get('/users/export/pdf',          [AdminUserController::class, 'exportUsersPdf'],      name: 'admin.users.export_pdf', permission: 'employees.view');
    $r->get('/users/export/pdf/download', [AdminUserController::class, 'exportUsersPdfDownload'], name: 'admin.users.export_pdf_download', permission: 'employees.view');
    $r->get('/users/export/json',         [AdminUserController::class, 'exportUsersJson'],     name: 'admin.users.export_json', permission: 'employees.view');
    $r->get('/users/create',              [AdminUserController::class, 'createUser'],          name: 'admin.users.create', permission: 'employees.create');
    $r->post('/users/create',             [AdminUserController::class, 'storeUser'],           name: 'admin.users.store', permission: 'employees.create');
    $r->post('/users/quick-create',       [AdminUserController::class, 'quickCreateUser'],     name: 'admin.users.quick_create', permission: 'employees.create');
    $r->get('/users/check-employee-code', [AdminUserController::class, 'checkEmployeeCode'],   name: 'admin.users.check_employee_code', permission: 'employees.view');
    $r->get('/users/check-email',         [AdminUserController::class, 'checkEmail'],          name: 'admin.users.check_email', permission: 'employees.view');
    $r->get('/users/{id}/edit',           [AdminUserController::class, 'editUser'],            name: 'admin.users.edit', permission: 'employees.view');
    $r->post('/users/{id}/edit',          [AdminUserController::class, 'updateUser'],          name: 'admin.users.update', permission: 'employees.update');
    $r->post('/users/{id}/delete',        [AdminUserController::class, 'deleteUser'],          name: 'admin.users.delete', permission: 'employees.delete');
    $r->post('/users/{id}/reset-password',[AdminUserController::class, 'resetPassword'],       name: 'admin.users.reset_password', permission: 'employees.update');
    $r->post('/users/{id}/rates',         [AdminUserController::class, 'setUserRate'],         name: 'admin.users.rates.set', permission: 'employees.update');
    $r->post('/users/{id}/rates/{rid}/delete', [AdminUserController::class, 'deleteUserRate'], name: 'admin.users.rates.delete', permission: 'employees.update');

    // Magasins
    $r->get('/stores',                    [AdminStoreController::class, 'stores'],               name: 'admin.stores', permission: 'stores.view');
    $r->get('/stores/create',             [AdminStoreController::class, 'createStore'],          name: 'admin.stores.create', permission: 'stores.create');
    $r->post('/stores/create',            [AdminStoreController::class, 'storeStore'],           name: 'admin.stores.store', permission: 'stores.create');
    $r->get('/stores/{id}/edit',          [AdminStoreController::class, 'editStore'],            name: 'admin.stores.edit', permission: 'stores.view');
    $r->post('/stores/{id}/edit',         [AdminStoreController::class, 'updateStore'],          name: 'admin.stores.update', permission: 'stores.update');
    $r->post('/stores/{id}/delete',       [AdminStoreController::class, 'deleteStore'],          name: 'admin.stores.delete', permission: 'stores.delete');
    $r->post('/stores/{id}/members',      [AdminStoreController::class, 'addMember'],            name: 'admin.stores.members.add', permission: 'employees.update');
    $r->post('/stores/{id}/members/{mid}/role',   [AdminStoreController::class, 'updateMemberRole'], name: 'admin.stores.members.role', permission: 'employees.update');
    $r->post('/stores/{id}/members/{mid}/delete', [AdminStoreController::class, 'removeMember'],      name: 'admin.stores.members.delete', permission: 'employees.update');
    $r->get('/stores/{id}/members/{mid}/deductions',  [AdminStoreController::class, 'editMemberDeductions'],   name: 'admin.stores.members.deductions', permission: 'payroll.view');
    $r->post('/stores/{id}/members/{mid}/deductions', [AdminStoreController::class, 'saveMemberDeductions'],   name: 'admin.stores.members.deductions.save', permission: 'payroll.generate');

    // Statistiques & Rapports
    $r->get('/stores/{id}/stats',              [AdminStoreController::class, 'storeStats'],       name: 'admin.stores.stats', permission: 'payroll.view');
    $r->get('/stores/{id}/stats/export',       [AdminStoreController::class, 'storeStatsExport'], name: 'admin.stores.stats_export', permission: 'payroll.export');
    $r->get('/stores/{id}/profitability',      [AdminStoreController::class, 'storeProfitability'], name: 'admin.stores.profitability', permission: 'payroll.view');
    $r->get('/stores/{id}/employee-report',                      [AdminStoreController::class, 'employeeReport'],    name: 'admin.stores.employee_report', permission: 'payroll.view');
    $r->get('/stores/{id}/employee-report/{uid}/stats',          [AdminStoreController::class, 'employeeStats'],     name: 'admin.stores.employee_stats', permission: 'payroll.view');

    // Rapports d'embauche : voir src/Bundles/HiringReport/routes.php

    // Démission : voir src/Bundles/ResignationReport/routes.php
    // Salaire : voir src/Bundles/SalaryReport/routes.php

    // Shift types
    $r->get('/shift-types',               [AdminShiftTypeController::class, 'shiftTypes'],         name: 'admin.shift_types', permission: 'shifts.view');
    $r->get('/shift-types/create',        [AdminShiftTypeController::class, 'createShiftType'],    name: 'admin.shift_types.create', permission: 'shifts.update');
    $r->post('/shift-types/create',       [AdminShiftTypeController::class, 'storeShiftType'],     name: 'admin.shift_types.store', permission: 'shifts.update');
    $r->get('/shift-types/{id}/edit',     [AdminShiftTypeController::class, 'editShiftType'],      name: 'admin.shift_types.edit', permission: 'shifts.update');
    $r->post('/shift-types/{id}/edit',    [AdminShiftTypeController::class, 'updateShiftType'],    name: 'admin.shift_types.update', permission: 'shifts.update');
    $r->post('/shift-types/{id}/delete',  [AdminShiftTypeController::class, 'deleteShiftType'],    name: 'admin.shift_types.delete', permission: 'shifts.update');
    $r->post('/shift-types/{id}/toggle-store', [AdminShiftTypeController::class, 'toggleShiftTypeStore'], name: 'admin.shift_types.toggle_store', permission: 'shifts.update');

    // Shifts
    $r->get('/shifts',              [AdminShiftController::class, 'shifts'],               name: 'admin.shifts', permission: 'shifts.view');
    $r->get('/shifts/calendar',     [AdminShiftController::class, 'shiftsCalendar'],       name: 'admin.shifts.calendar', permission: 'shifts.view');
    $r->get('/shifts/timeline',     [AdminShiftController::class, 'shiftsTimeline'],       name: 'admin.shifts.timeline', permission: 'shifts.view');
    $r->get('/shifts/timeline/print', [AdminShiftController::class, 'shiftsTimelinePrint'], name: 'admin.shifts.timeline.print', permission: 'shifts.view');
    $r->get('/shifts/conflicts',    [AdminShiftController::class, 'shiftConflicts'],       name: 'admin.shifts.conflicts', permission: 'shifts.view');
    $r->post('/shifts/conflicts/resolve-newer', [AdminShiftController::class, 'resolveNewerConflict'], name: 'admin.shifts.resolve_newer', permission: 'shifts.update');

    $r->get('/shifts/import',       [AdminShiftImportController::class, 'importShifts'],         name: 'admin.shifts.import', permission: 'shifts.import');
    $r->post('/shifts/import',      [AdminShiftImportController::class, 'processImport'],        name: 'admin.shifts.import.process', permission: 'shifts.import');
    $r->post('/shifts/import/confirm', [AdminShiftImportController::class, 'confirmImport'],     name: 'admin.shifts.import.confirm', permission: 'shifts.import');
    $r->get('/shifts/create',       [AdminShiftController::class, 'createShift'],          name: 'admin.shifts.create', permission: 'shifts.create');
    $r->get('/shifts/wage-preview', [AdminShiftController::class, 'wagePreview'],          name: 'admin.shifts.wage_preview', permission: 'shifts.view');
    $r->post('/shifts/create',      [AdminShiftController::class, 'storeShift'],           name: 'admin.shifts.store', permission: 'shifts.create');
    $r->post('/shifts/quick',       [AdminShiftController::class, 'quickShift'],           name: 'admin.shifts.quick', permission: 'shifts.create');
    $r->post('/shifts/bulk-delete', [AdminShiftController::class, 'bulkDeleteShifts'],     name: 'admin.shifts.bulk_delete', permission: 'shifts.delete');
    $r->get('/shifts/{id}/edit',    [AdminShiftController::class, 'editShift'],            name: 'admin.shifts.edit', permission: 'shifts.view');
    $r->post('/shifts/{id}/edit',   [AdminShiftController::class, 'updateShift'],          name: 'admin.shifts.update', permission: 'shifts.update');
    $r->post('/shifts/{id}/delete', [AdminShiftController::class, 'deleteShift'],          name: 'admin.shifts.delete', permission: 'shifts.delete');
    $r->post('/shifts/{id}/move',   [AdminShiftController::class, 'moveShift'],            name: 'admin.shifts.move', permission: 'shifts.update');

    // Bourse aux shifts : voir src/Bundles/ShiftClaim/routes.php

    // Demandes de congé : voir src/Bundles/TimeOff/routes.php

    // Échanges de shifts : voir src/Bundles/ShiftSwap/routes.php

    // Pointage : voir src/Bundles/Timeclock/routes.php

    // Journal d'activité (unifié)
    $r->get('/activity', [ActivityController::class, 'index'], name: 'admin.activity', permission: 'stores.view');

    // Feedbacks : voir src/Bundles/Feedback/routes.php

    // Diagnostic mail
    $r->get('/mail-test',  [MailTestController::class, 'show'], name: 'admin.mail_test', permission: 'public');
    $r->post('/mail-test', [MailTestController::class, 'send'], name: 'admin.mail_test.send', permission: 'public');

    // Sauvegardes
    $r->get('/backup',               [BackupController::class, 'index'],  name: 'admin.backup', permission: 'public');
    $r->get('/backup/download',      [BackupController::class, 'download'], name: 'admin.backup.download', permission: 'public');
    $r->post('/backup/create',       [BackupController::class, 'create'], name: 'admin.backup.create', permission: 'public');
    $r->post('/backup/restore',      [BackupController::class, 'restore'], name: 'admin.backup.restore', permission: 'public');
    $r->post('/backup/delete',       [BackupController::class, 'delete'], name: 'admin.backup.delete', permission: 'public');
    $r->post('/backup/delete-all',   [BackupController::class, 'deleteAll'], name: 'admin.backup.delete_all', permission: 'public');

    // Réinitialisation de l'application ("danger zone")
    $r->post('/reset/prepare', [AppResetController::class, 'prepare'], name: 'admin.reset.prepare', permission: 'public');
    $r->post('/reset/execute', [AppResetController::class, 'execute'], name: 'admin.reset.execute', permission: 'public');

    // Mises à jour
    $r->get('/update',               [BackupController::class, 'updatePage'], name: 'admin.update', permission: 'public');
    $r->post('/update/apply',        [BackupController::class, 'update'], name: 'admin.update.apply', permission: 'public');
    $r->post('/update/stream',       [BackupController::class, 'updateStream'], name: 'admin.update.stream', permission: 'public');
    $r->post('/update/migrate',      [BackupController::class, 'migrate'], name: 'admin.update.migrate', permission: 'public');
    $r->post('/update/channel',      [BackupController::class, 'saveChannel'], name: 'admin.update.channel', permission: 'public');

    // Photos : voir src/Bundles/StorePhoto/routes.php

    // Langues & traductions (Owner uniquement)
    $r->get('/languages',                       [LanguageController::class, 'index'],        name: 'admin.languages', permission: 'public');
    $r->post('/languages',                      [LanguageController::class, 'store'],        name: 'admin.languages.store', permission: 'public');
    $r->post('/languages/{code}/default',       [LanguageController::class, 'setDefault'],   name: 'admin.languages.set_default', permission: 'public');
    $r->post('/languages/{code}/toggle-active', [LanguageController::class, 'toggleActive'], name: 'admin.languages.toggle_active', permission: 'public');
    $r->post('/languages/{code}/delete',        [LanguageController::class, 'destroy'],      name: 'admin.languages.delete', permission: 'public');
    $r->get('/languages/{code}/edit',           [LanguageController::class, 'edit'],         name: 'admin.languages.edit', permission: 'public');
    $r->post('/languages/{code}/edit/save',     [LanguageController::class, 'saveKey'],      name: 'admin.languages.edit.save', permission: 'public');
    $r->post('/languages/{code}/edit/delete',   [LanguageController::class, 'deleteKey'],    name: 'admin.languages.edit.delete', permission: 'public');

    // Bundles (Owner uniquement)
    $r->get('/bundles',  [BundleSettingsController::class, 'show'], name: 'admin.bundles', permission: 'public');
    $r->post('/bundles', [BundleSettingsController::class, 'save'], name: 'admin.bundles.save', permission: 'public');

    // Rôles & permissions (Owner uniquement) — voir task/mermission.md
    $r->get('/roles',              [AdminRoleController::class, 'roles'],      name: 'admin.roles', permission: 'public');
    $r->get('/roles/create',       [AdminRoleController::class, 'createRole'], name: 'admin.roles.create', permission: 'public');
    $r->post('/roles/create',      [AdminRoleController::class, 'storeRole'],  name: 'admin.roles.store', permission: 'public');
    $r->get('/roles/{id}/edit',    [AdminRoleController::class, 'editRole'],   name: 'admin.roles.edit', permission: 'public');
    $r->post('/roles/{id}/edit',   [AdminRoleController::class, 'updateRole'], name: 'admin.roles.update', permission: 'public');
    $r->post('/roles/{id}/delete', [AdminRoleController::class, 'deleteRole'], name: 'admin.roles.delete', permission: 'public');

}, middleware: [AuthMiddleware::class, PermissionMiddleware::class]);

// =============================================================================
// API v1
// =============================================================================

// --- Routes publiques ---
$router->get('/api/v1/ping',       [ApiAuthController::class, 'ping'],  name: 'api.v1.ping');
$router->post('/api/v1/auth/login', [ApiAuthController::class, 'login'], name: 'api.v1.auth.login');

// --- Routes protégées par token Bearer ---
$router->group('/api/v1', function ($r) {

    // Auth
    $r->post('/auth/logout',        [ApiAuthController::class, 'logout'],      name: 'api.v1.auth.logout', permission: 'public');
    $r->get('/auth/me',             [ApiAuthController::class, 'me'],          name: 'api.v1.auth.me', permission: 'public');
    $r->get('/auth/tokens',         [ApiAuthController::class, 'listTokens'],  name: 'api.v1.auth.tokens', permission: 'public');
    $r->delete('/auth/tokens/{id}', [ApiAuthController::class, 'revokeToken'], name: 'api.v1.auth.tokens.revoke', permission: 'public');

    // Users
    $r->get('/users',         [ApiUserController::class, 'index'],   name: 'api.v1.users.index', permission: 'employees.view');
    $r->post('/users',        [ApiUserController::class, 'store'],   name: 'api.v1.users.store', permission: 'employees.create');
    $r->get('/users/{id}',    [ApiUserController::class, 'show'],    name: 'api.v1.users.show', permission: ['perm' => 'employees.view', 'self' => 'id']);
    $r->put('/users/{id}',    [ApiUserController::class, 'update'],  name: 'api.v1.users.update', permission: 'employees.update');
    $r->delete('/users/{id}', [ApiUserController::class, 'destroy'], name: 'api.v1.users.destroy', permission: 'employees.delete');

    // Users — prefs, rates, ical-tokens
    $r->get('/users/{user_id}/dashboard-prefs',                       [ApiUserPrefsController::class, 'getDashboardPrefs'],  name: 'api.v1.users.dashboard_prefs.get', permission: ['perm' => 'employees.view', 'self' => 'user_id']);
    $r->put('/users/{user_id}/dashboard-prefs',                       [ApiUserPrefsController::class, 'saveDashboardPrefs'], name: 'api.v1.users.dashboard_prefs.save', permission: ['perm' => 'employees.update', 'self' => 'user_id']);
    $r->get('/users/{user_id}/nav-prefs',                             [ApiUserPrefsController::class, 'getNavPrefs'],        name: 'api.v1.users.nav_prefs.get', permission: ['perm' => 'employees.view', 'self' => 'user_id']);
    $r->put('/users/{user_id}/nav-prefs',                             [ApiUserPrefsController::class, 'saveNavPrefs'],       name: 'api.v1.users.nav_prefs.save', permission: ['perm' => 'employees.update', 'self' => 'user_id']);
    $r->get('/users/{user_id}/rates',                                 [ApiUserShiftRateController::class, 'index'],          name: 'api.v1.users.rates.index', permission: ['perm' => 'payroll.view', 'self' => 'user_id']);
    $r->post('/users/{user_id}/rates',                                [ApiUserShiftRateController::class, 'store'],          name: 'api.v1.users.rates.store', permission: 'employees.update');
    $r->get('/users/{user_id}/rates/{id}',                            [ApiUserShiftRateController::class, 'show'],           name: 'api.v1.users.rates.show', permission: ['perm' => 'payroll.view', 'self' => 'user_id']);
    $r->put('/users/{user_id}/rates/{id}',                            [ApiUserShiftRateController::class, 'update'],         name: 'api.v1.users.rates.update', permission: 'employees.update');
    $r->delete('/users/{user_id}/rates/{id}',                         [ApiUserShiftRateController::class, 'destroy'],        name: 'api.v1.users.rates.destroy', permission: 'employees.update');
    $r->get('/users/{user_id}/ical-tokens',                           [ApiIcalTokenController::class, 'index'],              name: 'api.v1.users.ical_tokens.index', permission: ['perm' => 'employees.view', 'self' => 'user_id']);
    $r->post('/users/{user_id}/ical-tokens',                          [ApiIcalTokenController::class, 'store'],              name: 'api.v1.users.ical_tokens.store', permission: ['perm' => 'employees.update', 'self' => 'user_id']);
    $r->post('/users/{user_id}/ical-tokens/{store_id}/regenerate',    [ApiIcalTokenController::class, 'regenerate'],         name: 'api.v1.users.ical_tokens.regenerate', permission: ['perm' => 'employees.update', 'self' => 'user_id']);
    $r->get('/users/{user_id}/ical-tokens/{store_id}',                [ApiIcalTokenController::class, 'show'],               name: 'api.v1.users.ical_tokens.show', permission: ['perm' => 'employees.view', 'self' => 'user_id']);
    $r->delete('/users/{user_id}/ical-tokens/{store_id}',             [ApiIcalTokenController::class, 'destroy'],            name: 'api.v1.users.ical_tokens.destroy', permission: ['perm' => 'employees.update', 'self' => 'user_id']);

    // Stores
    $r->get('/stores',         [ApiStoreController::class, 'index'],   name: 'api.v1.stores.index', permission: 'stores.view');
    $r->post('/stores',        [ApiStoreController::class, 'store'],   name: 'api.v1.stores.store', permission: 'stores.create');
    $r->get('/stores/{id}',    [ApiStoreController::class, 'show'],    name: 'api.v1.stores.show', permission: 'stores.view');
    $r->put('/stores/{id}',    [ApiStoreController::class, 'update'],  name: 'api.v1.stores.update', permission: 'stores.update');
    $r->delete('/stores/{id}', [ApiStoreController::class, 'destroy'], name: 'api.v1.stores.destroy', permission: 'stores.delete');

    // Stores — membres
    $r->get('/stores/{store_id}/members',         [ApiStoreUserController::class, 'index'],   name: 'api.v1.store_members.index', permission: 'employees.view');
    $r->post('/stores/{store_id}/members',        [ApiStoreUserController::class, 'store'],   name: 'api.v1.store_members.store', permission: 'employees.update');
    $r->get('/stores/{store_id}/members/{id}',    [ApiStoreUserController::class, 'show'],    name: 'api.v1.store_members.show', permission: 'employees.view');
    $r->put('/stores/{store_id}/members/{id}',    [ApiStoreUserController::class, 'update'],  name: 'api.v1.store_members.update', permission: 'employees.update');
    $r->delete('/stores/{store_id}/members/{id}', [ApiStoreUserController::class, 'destroy'], name: 'api.v1.store_members.destroy', permission: 'employees.update');

    // Shift types
    $r->get('/shift-types',         [ApiShiftTypeController::class, 'index'],   name: 'api.v1.shift_types.index', permission: 'shifts.view');
    $r->post('/shift-types',        [ApiShiftTypeController::class, 'store'],   name: 'api.v1.shift_types.store', permission: 'shifts.update');
    $r->get('/shift-types/{id}',    [ApiShiftTypeController::class, 'show'],    name: 'api.v1.shift_types.show', permission: 'shifts.view');
    $r->put('/shift-types/{id}',    [ApiShiftTypeController::class, 'update'],  name: 'api.v1.shift_types.update', permission: 'shifts.update');
    $r->delete('/shift-types/{id}', [ApiShiftTypeController::class, 'destroy'], name: 'api.v1.shift_types.destroy', permission: 'shifts.update');

    // Shifts
    $r->get('/shifts',         [ApiShiftController::class, 'index'],   name: 'api.v1.shifts.index', permission: 'shifts.view');
    $r->post('/shifts',        [ApiShiftController::class, 'store'],   name: 'api.v1.shifts.store', permission: 'shifts.create');
    $r->get('/shifts/{id}',    [ApiShiftController::class, 'show'],    name: 'api.v1.shifts.show', permission: 'shifts.view');
    $r->put('/shifts/{id}',    [ApiShiftController::class, 'update'],  name: 'api.v1.shifts.update', permission: 'shifts.update');
    $r->delete('/shifts/{id}', [ApiShiftController::class, 'destroy'], name: 'api.v1.shifts.destroy', permission: 'shifts.delete');

    // Disponibilités
    $r->get('/availabilities',         [ApiAvailabilityController::class, 'index'],   name: 'api.v1.availabilities.index', permission: 'shifts.view');
    $r->post('/availabilities',        [ApiAvailabilityController::class, 'store'],   name: 'api.v1.availabilities.store', permission: 'shifts.update');
    $r->get('/availabilities/{id}',    [ApiAvailabilityController::class, 'show'],    name: 'api.v1.availabilities.show', permission: 'shifts.view');
    $r->put('/availabilities/{id}',    [ApiAvailabilityController::class, 'update'],  name: 'api.v1.availabilities.update', permission: 'shifts.update');
    $r->delete('/availabilities/{id}', [ApiAvailabilityController::class, 'destroy'], name: 'api.v1.availabilities.destroy', permission: 'shifts.update');

    // Demandes de congé : voir src/Bundles/TimeOff/routes.php

    // Échanges de shifts : voir src/Bundles/ShiftSwap/routes.php

    // Pointage : voir src/Bundles/Timeclock/routes.php

    // Bourse aux shifts : voir src/Bundles/ShiftClaim/routes.php

    // Notifications
    $r->post('/notifications/read-all',    [ApiNotificationController::class, 'markAllRead'], name: 'api.v1.notifications.read_all', permission: 'public');
    $r->get('/notifications',              [ApiNotificationController::class, 'index'],       name: 'api.v1.notifications.index', permission: 'public');
    $r->get('/notifications/{id}',         [ApiNotificationController::class, 'show'],        name: 'api.v1.notifications.show', permission: 'public');
    $r->post('/notifications/{id}/read',   [ApiNotificationController::class, 'markRead'],    name: 'api.v1.notifications.read', permission: 'public');
    $r->delete('/notifications/{id}',      [ApiNotificationController::class, 'destroy'],     name: 'api.v1.notifications.destroy', permission: 'public');

    // Feedbacks : voir src/Bundles/Feedback/routes.php

    // Journal d'activité
    $r->get('/activity', [ApiActivityController::class, 'index'], name: 'api.v1.activity.index', permission: 'stores.view');

}, middleware: [ApiAuthMiddleware::class, ApiPermissionMiddleware::class]);
