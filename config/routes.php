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

use kintai\UI\Controller\Web\Scheduling\AdminShiftController;
use kintai\UI\Controller\Web\Scheduling\AdminShiftImportController;
use kintai\UI\Controller\Web\Scheduling\AdminShiftTypeController;

use kintai\UI\Controller\Web\Staff\AdminStoreController;
use kintai\UI\Controller\Web\Staff\AdminUserController;

use kintai\UI\Controller\Web\System\ActivityController;
use kintai\UI\Controller\Web\System\AdminController;
use kintai\UI\Controller\Web\System\AdminRoleController;
use kintai\UI\Controller\Web\System\BackupController;
use kintai\UI\Controller\Web\System\BundleSettingsController;
use kintai\UI\Controller\Web\System\LanguageController;
use kintai\UI\Controller\Web\System\MailTestController;
use kintai\UI\Controller\Web\System\OwnerSettingsController;
use kintai\Core\Middleware\AuthMiddleware;
use kintai\Core\Middleware\AdminMiddleware;
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
$router->get('/storage/{path*}', [StorageFileController::class, 'serve'], middleware: [AuthMiddleware::class, AdminMiddleware::class], name: 'storage.file');

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
$router->get('/', [HomeController::class, 'index'], middleware: [AuthMiddleware::class, AdminMiddleware::class, PermissionMiddleware::class], name: 'home');
$router->post('/admin/dashboard/widgets', [HomeController::class, 'saveDashboardWidgets'], middleware: [AuthMiddleware::class, AdminMiddleware::class], name: 'admin.dashboard.widgets');

// =============================================================================
// Routes administration (web)
// =============================================================================

$router->group('/admin', function ($r) {

    // Configuration organisation
    $r->get('/owner-settings',  [OwnerSettingsController::class, 'show'], name: 'admin.owner_settings');
    $r->post('/owner-settings', [OwnerSettingsController::class, 'save'], name: 'admin.owner_settings.save');

    // Navigation
    $r->get('/nav-settings',  [AdminController::class, 'navSettings'],     name: 'admin.nav_settings');
    $r->post('/nav-settings', [AdminController::class, 'saveNavSettings'], name: 'admin.nav_settings.save');

    // Utilisateurs
    $r->get('/users',                     [AdminUserController::class, 'users'],               name: 'admin.users');
    $r->get('/users/create',              [AdminUserController::class, 'createUser'],          name: 'admin.users.create');
    $r->post('/users/create',             [AdminUserController::class, 'storeUser'],           name: 'admin.users.store');
    $r->post('/users/quick-create',       [AdminUserController::class, 'quickCreateUser'],     name: 'admin.users.quick_create');
    $r->get('/users/check-employee-code', [AdminUserController::class, 'checkEmployeeCode'],   name: 'admin.users.check_employee_code');
    $r->get('/users/{id}/edit',           [AdminUserController::class, 'editUser'],            name: 'admin.users.edit');
    $r->post('/users/{id}/edit',          [AdminUserController::class, 'updateUser'],          name: 'admin.users.update');
    $r->post('/users/{id}/delete',        [AdminUserController::class, 'deleteUser'],          name: 'admin.users.delete');
    $r->post('/users/{id}/reset-password',[AdminUserController::class, 'resetPassword'],       name: 'admin.users.reset_password');
    $r->post('/users/{id}/rates',         [AdminUserController::class, 'setUserRate'],         name: 'admin.users.rates.set');
    $r->post('/users/{id}/rates/{rid}/delete', [AdminUserController::class, 'deleteUserRate'], name: 'admin.users.rates.delete');

    // Magasins
    $r->get('/stores',                    [AdminStoreController::class, 'stores'],               name: 'admin.stores');
    $r->get('/stores/create',             [AdminStoreController::class, 'createStore'],          name: 'admin.stores.create');
    $r->post('/stores/create',            [AdminStoreController::class, 'storeStore'],           name: 'admin.stores.store');
    $r->get('/stores/{id}/edit',          [AdminStoreController::class, 'editStore'],            name: 'admin.stores.edit');
    $r->post('/stores/{id}/edit',         [AdminStoreController::class, 'updateStore'],          name: 'admin.stores.update');
    $r->post('/stores/{id}/delete',       [AdminStoreController::class, 'deleteStore'],          name: 'admin.stores.delete');
    $r->post('/stores/{id}/members',      [AdminStoreController::class, 'addMember'],            name: 'admin.stores.members.add');
    $r->post('/stores/{id}/members/{mid}/role',   [AdminStoreController::class, 'updateMemberRole'], name: 'admin.stores.members.role');
    $r->post('/stores/{id}/members/{mid}/delete', [AdminStoreController::class, 'removeMember'],      name: 'admin.stores.members.delete');
    $r->get('/stores/{id}/members/{mid}/deductions',  [AdminStoreController::class, 'editMemberDeductions'],   name: 'admin.stores.members.deductions');
    $r->post('/stores/{id}/members/{mid}/deductions', [AdminStoreController::class, 'saveMemberDeductions'],   name: 'admin.stores.members.deductions.save');

    // Statistiques & Rapports
    $r->get('/stores/{id}/stats',              [AdminStoreController::class, 'storeStats'],       name: 'admin.stores.stats');
    $r->get('/stores/{id}/stats/export',       [AdminStoreController::class, 'storeStatsExport'], name: 'admin.stores.stats_export');
    $r->get('/stores/{id}/profitability',      [AdminStoreController::class, 'storeProfitability'], name: 'admin.stores.profitability');
    $r->get('/stores/{id}/employee-report',                      [AdminStoreController::class, 'employeeReport'],    name: 'admin.stores.employee_report');
    $r->get('/stores/{id}/employee-report/{uid}/stats',          [AdminStoreController::class, 'employeeStats'],     name: 'admin.stores.employee_stats');
    $r->get('/stores/{id}/employee-report/{uid}/payslip',        [AdminStoreController::class, 'employeePayslip'],   name: 'admin.stores.employee_payslip');
    $r->get('/stores/{id}/employee-report/{uid}/payslip/pdf',    [AdminStoreController::class, 'employeePayslipPdf'], name: 'admin.stores.employee_payslip_pdf');

    // Rapports d'embauche : voir src/Bundles/HiringReport/routes.php

    // Démission : voir src/Bundles/ResignationReport/routes.php
    // Salaire : voir src/Bundles/SalaryReport/routes.php

    // Shift types
    $r->get('/shift-types',               [AdminShiftTypeController::class, 'shiftTypes'],         name: 'admin.shift_types');
    $r->get('/shift-types/create',        [AdminShiftTypeController::class, 'createShiftType'],    name: 'admin.shift_types.create');
    $r->post('/shift-types/create',       [AdminShiftTypeController::class, 'storeShiftType'],     name: 'admin.shift_types.store');
    $r->get('/shift-types/{id}/edit',     [AdminShiftTypeController::class, 'editShiftType'],      name: 'admin.shift_types.edit');
    $r->post('/shift-types/{id}/edit',    [AdminShiftTypeController::class, 'updateShiftType'],    name: 'admin.shift_types.update');
    $r->post('/shift-types/{id}/delete',  [AdminShiftTypeController::class, 'deleteShiftType'],    name: 'admin.shift_types.delete');

    // Shifts
    $r->get('/shifts',              [AdminShiftController::class, 'shifts'],               name: 'admin.shifts');
    $r->get('/shifts/calendar',     [AdminShiftController::class, 'shiftsCalendar'],       name: 'admin.shifts.calendar');
    $r->get('/shifts/timeline',     [AdminShiftController::class, 'shiftsTimeline'],       name: 'admin.shifts.timeline');
    $r->get('/shifts/timeline/print', [AdminShiftController::class, 'shiftsTimelinePrint'], name: 'admin.shifts.timeline.print');
    $r->get('/shifts/conflicts',    [AdminShiftController::class, 'shiftConflicts'],       name: 'admin.shifts.conflicts');
    $r->post('/shifts/conflicts/resolve-newer', [AdminShiftController::class, 'resolveNewerConflict'], name: 'admin.shifts.resolve_newer');

    $r->get('/shifts/import',       [AdminShiftImportController::class, 'importShifts'],         name: 'admin.shifts.import');
    $r->post('/shifts/import',      [AdminShiftImportController::class, 'processImport'],        name: 'admin.shifts.import.process');
    $r->post('/shifts/import/confirm', [AdminShiftImportController::class, 'confirmImport'],     name: 'admin.shifts.import.confirm');
    $r->get('/shifts/create',       [AdminShiftController::class, 'createShift'],          name: 'admin.shifts.create');
    $r->post('/shifts/create',      [AdminShiftController::class, 'storeShift'],           name: 'admin.shifts.store');
    $r->post('/shifts/quick',       [AdminShiftController::class, 'quickShift'],           name: 'admin.shifts.quick');
    $r->post('/shifts/bulk-delete', [AdminShiftController::class, 'bulkDeleteShifts'],     name: 'admin.shifts.bulk_delete');
    $r->get('/shifts/{id}/edit',    [AdminShiftController::class, 'editShift'],            name: 'admin.shifts.edit');
    $r->post('/shifts/{id}/edit',   [AdminShiftController::class, 'updateShift'],          name: 'admin.shifts.update');
    $r->post('/shifts/{id}/delete', [AdminShiftController::class, 'deleteShift'],          name: 'admin.shifts.delete');
    $r->post('/shifts/{id}/move',   [AdminShiftController::class, 'moveShift'],            name: 'admin.shifts.move');

    // Bourse aux shifts : voir src/Bundles/ShiftClaim/routes.php

    // Demandes de congé : voir src/Bundles/TimeOff/routes.php

    // Échanges de shifts : voir src/Bundles/ShiftSwap/routes.php

    // Pointage : voir src/Bundles/Timeclock/routes.php

    // Journal d'activité (unifié)
    $r->get('/activity', [ActivityController::class, 'index'], name: 'admin.activity');

    // Feedbacks : voir src/Bundles/Feedback/routes.php

    // Diagnostic mail
    $r->get('/mail-test',  [MailTestController::class, 'show'], name: 'admin.mail_test');
    $r->post('/mail-test', [MailTestController::class, 'send'], name: 'admin.mail_test.send');

    // Sauvegardes
    $r->get('/backup',               [BackupController::class, 'index'],  name: 'admin.backup');
    $r->post('/backup/create',       [BackupController::class, 'create'], name: 'admin.backup.create');
    $r->post('/backup/restore',      [BackupController::class, 'restore'], name: 'admin.backup.restore');
    $r->post('/backup/delete',       [BackupController::class, 'delete'], name: 'admin.backup.delete');
    $r->post('/backup/delete-all',   [BackupController::class, 'deleteAll'], name: 'admin.backup.delete_all');

    // Mises à jour
    $r->get('/update',               [BackupController::class, 'updatePage'], name: 'admin.update');
    $r->post('/update/apply',        [BackupController::class, 'update'], name: 'admin.update.apply');
    $r->post('/update/stream',       [BackupController::class, 'updateStream'], name: 'admin.update.stream');
    $r->post('/update/migrate',      [BackupController::class, 'migrate'], name: 'admin.update.migrate');
    $r->post('/update/channel',      [BackupController::class, 'saveChannel'], name: 'admin.update.channel');

    // Photos : voir src/Bundles/StorePhoto/routes.php

    // Langues & traductions (Owner uniquement)
    $r->get('/languages',                       [LanguageController::class, 'index'],        name: 'admin.languages');
    $r->post('/languages',                      [LanguageController::class, 'store'],        name: 'admin.languages.store');
    $r->post('/languages/{code}/default',       [LanguageController::class, 'setDefault'],   name: 'admin.languages.set_default');
    $r->post('/languages/{code}/toggle-active', [LanguageController::class, 'toggleActive'], name: 'admin.languages.toggle_active');
    $r->post('/languages/{code}/delete',        [LanguageController::class, 'destroy'],      name: 'admin.languages.delete');
    $r->get('/languages/{code}/edit',           [LanguageController::class, 'edit'],         name: 'admin.languages.edit');
    $r->post('/languages/{code}/edit/save',     [LanguageController::class, 'saveKey'],      name: 'admin.languages.edit.save');
    $r->post('/languages/{code}/edit/delete',   [LanguageController::class, 'deleteKey'],    name: 'admin.languages.edit.delete');

    // Bundles (Owner uniquement)
    $r->get('/bundles',  [BundleSettingsController::class, 'show'], name: 'admin.bundles');
    $r->post('/bundles', [BundleSettingsController::class, 'save'], name: 'admin.bundles.save');

    // Rôles & permissions (Owner uniquement) — voir task/mermission.md
    $r->get('/roles',              [AdminRoleController::class, 'roles'],      name: 'admin.roles');
    $r->get('/roles/create',       [AdminRoleController::class, 'createRole'], name: 'admin.roles.create');
    $r->post('/roles/create',      [AdminRoleController::class, 'storeRole'],  name: 'admin.roles.store');
    $r->get('/roles/{id}/edit',    [AdminRoleController::class, 'editRole'],   name: 'admin.roles.edit');
    $r->post('/roles/{id}/edit',   [AdminRoleController::class, 'updateRole'], name: 'admin.roles.update');
    $r->post('/roles/{id}/delete', [AdminRoleController::class, 'deleteRole'], name: 'admin.roles.delete');

}, middleware: [AuthMiddleware::class, AdminMiddleware::class, PermissionMiddleware::class]);

// =============================================================================
// API v1
// =============================================================================

// --- Routes publiques ---
$router->get('/api/v1/ping',       [ApiAuthController::class, 'ping'],  name: 'api.v1.ping');
$router->post('/api/v1/auth/login', [ApiAuthController::class, 'login'], name: 'api.v1.auth.login');

// --- Routes protégées par token Bearer ---
$router->group('/api/v1', function ($r) {

    // Auth
    $r->post('/auth/logout',        [ApiAuthController::class, 'logout'],      name: 'api.v1.auth.logout');
    $r->get('/auth/me',             [ApiAuthController::class, 'me'],          name: 'api.v1.auth.me');
    $r->get('/auth/tokens',         [ApiAuthController::class, 'listTokens'],  name: 'api.v1.auth.tokens');
    $r->delete('/auth/tokens/{id}', [ApiAuthController::class, 'revokeToken'], name: 'api.v1.auth.tokens.revoke');

    // Users
    $r->get('/users',         [ApiUserController::class, 'index'],   name: 'api.v1.users.index');
    $r->post('/users',        [ApiUserController::class, 'store'],   name: 'api.v1.users.store');
    $r->get('/users/{id}',    [ApiUserController::class, 'show'],    name: 'api.v1.users.show');
    $r->put('/users/{id}',    [ApiUserController::class, 'update'],  name: 'api.v1.users.update');
    $r->delete('/users/{id}', [ApiUserController::class, 'destroy'], name: 'api.v1.users.destroy');

    // Users — prefs, rates, ical-tokens
    $r->get('/users/{user_id}/dashboard-prefs',                       [ApiUserPrefsController::class, 'getDashboardPrefs'],  name: 'api.v1.users.dashboard_prefs.get');
    $r->put('/users/{user_id}/dashboard-prefs',                       [ApiUserPrefsController::class, 'saveDashboardPrefs'], name: 'api.v1.users.dashboard_prefs.save');
    $r->get('/users/{user_id}/nav-prefs',                             [ApiUserPrefsController::class, 'getNavPrefs'],        name: 'api.v1.users.nav_prefs.get');
    $r->put('/users/{user_id}/nav-prefs',                             [ApiUserPrefsController::class, 'saveNavPrefs'],       name: 'api.v1.users.nav_prefs.save');
    $r->get('/users/{user_id}/rates',                                 [ApiUserShiftRateController::class, 'index'],          name: 'api.v1.users.rates.index');
    $r->post('/users/{user_id}/rates',                                [ApiUserShiftRateController::class, 'store'],          name: 'api.v1.users.rates.store');
    $r->get('/users/{user_id}/rates/{id}',                            [ApiUserShiftRateController::class, 'show'],           name: 'api.v1.users.rates.show');
    $r->put('/users/{user_id}/rates/{id}',                            [ApiUserShiftRateController::class, 'update'],         name: 'api.v1.users.rates.update');
    $r->delete('/users/{user_id}/rates/{id}',                         [ApiUserShiftRateController::class, 'destroy'],        name: 'api.v1.users.rates.destroy');
    $r->get('/users/{user_id}/ical-tokens',                           [ApiIcalTokenController::class, 'index'],              name: 'api.v1.users.ical_tokens.index');
    $r->post('/users/{user_id}/ical-tokens',                          [ApiIcalTokenController::class, 'store'],              name: 'api.v1.users.ical_tokens.store');
    $r->post('/users/{user_id}/ical-tokens/{store_id}/regenerate',    [ApiIcalTokenController::class, 'regenerate'],         name: 'api.v1.users.ical_tokens.regenerate');
    $r->get('/users/{user_id}/ical-tokens/{store_id}',                [ApiIcalTokenController::class, 'show'],               name: 'api.v1.users.ical_tokens.show');
    $r->delete('/users/{user_id}/ical-tokens/{store_id}',             [ApiIcalTokenController::class, 'destroy'],            name: 'api.v1.users.ical_tokens.destroy');

    // Stores
    $r->get('/stores',         [ApiStoreController::class, 'index'],   name: 'api.v1.stores.index');
    $r->post('/stores',        [ApiStoreController::class, 'store'],   name: 'api.v1.stores.store');
    $r->get('/stores/{id}',    [ApiStoreController::class, 'show'],    name: 'api.v1.stores.show');
    $r->put('/stores/{id}',    [ApiStoreController::class, 'update'],  name: 'api.v1.stores.update');
    $r->delete('/stores/{id}', [ApiStoreController::class, 'destroy'], name: 'api.v1.stores.destroy');

    // Stores — membres
    $r->get('/stores/{store_id}/members',         [ApiStoreUserController::class, 'index'],   name: 'api.v1.store_members.index');
    $r->post('/stores/{store_id}/members',        [ApiStoreUserController::class, 'store'],   name: 'api.v1.store_members.store');
    $r->get('/stores/{store_id}/members/{id}',    [ApiStoreUserController::class, 'show'],    name: 'api.v1.store_members.show');
    $r->put('/stores/{store_id}/members/{id}',    [ApiStoreUserController::class, 'update'],  name: 'api.v1.store_members.update');
    $r->delete('/stores/{store_id}/members/{id}', [ApiStoreUserController::class, 'destroy'], name: 'api.v1.store_members.destroy');

    // Shift types
    $r->get('/shift-types',         [ApiShiftTypeController::class, 'index'],   name: 'api.v1.shift_types.index');
    $r->post('/shift-types',        [ApiShiftTypeController::class, 'store'],   name: 'api.v1.shift_types.store');
    $r->get('/shift-types/{id}',    [ApiShiftTypeController::class, 'show'],    name: 'api.v1.shift_types.show');
    $r->put('/shift-types/{id}',    [ApiShiftTypeController::class, 'update'],  name: 'api.v1.shift_types.update');
    $r->delete('/shift-types/{id}', [ApiShiftTypeController::class, 'destroy'], name: 'api.v1.shift_types.destroy');

    // Shifts
    $r->get('/shifts',         [ApiShiftController::class, 'index'],   name: 'api.v1.shifts.index');
    $r->post('/shifts',        [ApiShiftController::class, 'store'],   name: 'api.v1.shifts.store');
    $r->get('/shifts/{id}',    [ApiShiftController::class, 'show'],    name: 'api.v1.shifts.show');
    $r->put('/shifts/{id}',    [ApiShiftController::class, 'update'],  name: 'api.v1.shifts.update');
    $r->delete('/shifts/{id}', [ApiShiftController::class, 'destroy'], name: 'api.v1.shifts.destroy');

    // Disponibilités
    $r->get('/availabilities',         [ApiAvailabilityController::class, 'index'],   name: 'api.v1.availabilities.index');
    $r->post('/availabilities',        [ApiAvailabilityController::class, 'store'],   name: 'api.v1.availabilities.store');
    $r->get('/availabilities/{id}',    [ApiAvailabilityController::class, 'show'],    name: 'api.v1.availabilities.show');
    $r->put('/availabilities/{id}',    [ApiAvailabilityController::class, 'update'],  name: 'api.v1.availabilities.update');
    $r->delete('/availabilities/{id}', [ApiAvailabilityController::class, 'destroy'], name: 'api.v1.availabilities.destroy');

    // Demandes de congé : voir src/Bundles/TimeOff/routes.php

    // Échanges de shifts : voir src/Bundles/ShiftSwap/routes.php

    // Pointage : voir src/Bundles/Timeclock/routes.php

    // Bourse aux shifts : voir src/Bundles/ShiftClaim/routes.php

    // Notifications
    $r->post('/notifications/read-all',    [ApiNotificationController::class, 'markAllRead'], name: 'api.v1.notifications.read_all');
    $r->get('/notifications',              [ApiNotificationController::class, 'index'],       name: 'api.v1.notifications.index');
    $r->get('/notifications/{id}',         [ApiNotificationController::class, 'show'],        name: 'api.v1.notifications.show');
    $r->post('/notifications/{id}/read',   [ApiNotificationController::class, 'markRead'],    name: 'api.v1.notifications.read');
    $r->delete('/notifications/{id}',      [ApiNotificationController::class, 'destroy'],     name: 'api.v1.notifications.destroy');

    // Feedbacks : voir src/Bundles/Feedback/routes.php

    // Journal d'activité
    $r->get('/activity', [ApiActivityController::class, 'index'], name: 'api.v1.activity.index');

}, middleware: [ApiAuthMiddleware::class, ApiPermissionMiddleware::class]);
