<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\Exco\ApprovalController;
use App\Http\Controllers\Exco\AttendanceController;
use App\Http\Controllers\Exco\DashboardController;
use App\Http\Controllers\Exco\EventReportController;
use App\Http\Controllers\Exco\MemberController;
use App\Http\Controllers\Exco\MeritController;
use App\Http\Controllers\Exco\AnnouncementController;
use App\Http\Controllers\EmailPreferenceController;
use App\Http\Controllers\MtController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Exco\ProgramManageController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\MeritPageController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\PwaController;
use Illuminate\Support\Facades\Route;

// ---- Public -------------------------------------------------------------
// A fresh security token for pages left open for days (the installed app).
Route::get('/csrf-token', fn () => response()->json(['token' => csrf_token()])->header('Cache-Control', 'no-store'))
    ->middleware('throttle:30,1')->name('csrf.token');

Route::get('/', [PublicController::class, 'home'])->name('home');

// App install (PWA): manifest, service worker, offline page and the how-to page.
Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/sw.js', [PwaController::class, 'serviceWorker'])->name('pwa.sw');
Route::get('/offline', [PwaController::class, 'offline'])->name('pwa.offline');
Route::get('/get-the-app', [PwaController::class, 'guide'])->name('pwa.guide');

// One-time web installer (for cPanel plans without SSH). Disabled once installed.
Route::get('/install', [InstallController::class, 'show'])->name('install.show');
Route::post('/install', [InstallController::class, 'run'])->name('install.run');

// The "turn off club news" link in club emails. Signed, so it works without logging in.
Route::get('/email/unsubscribe/{user}', [EmailPreferenceController::class, 'unsubscribe'])->middleware(['signed', 'throttle:20,1'])->name('email.unsubscribe');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login');
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:register');

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});

// ---- Signed in (any account state) --------------------------------------
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/account/pending', [AccountController::class, 'pending'])->name('account.pending');
    Route::get('/home', [AccountController::class, 'redirect'])->name('home.redirect');
});

// ---- Approved members -----------------------------------------------------
Route::middleware(['auth', 'approved'])->group(function () {
    Route::get('/portal', [PortalController::class, 'index'])->name('portal');

    Route::get('/programs', [ProgramController::class, 'index'])->name('programs.index');
    Route::get('/programs/{program}', [ProgramController::class, 'show'])->name('programs.show');
    Route::get('/programs/{program}/calendar.ics', [ProgramController::class, 'calendar'])->name('programs.calendar');
    Route::post('/programs/{program}/feedback', [FeedbackController::class, 'store'])->middleware('throttle:20,1')->name('programs.feedback');
    Route::post('/programs/{program}/register', [ProgramController::class, 'register'])->name('programs.register');
    Route::post('/programs/{program}/cancel', [ProgramController::class, 'cancel'])->name('programs.cancel');

    Route::get('/checkin/{code}', [CheckInController::class, 'show'])->name('checkin.show');
    Route::post('/checkin/{code}', [CheckInController::class, 'store'])->name('checkin.store');

    Route::get('/merit', [MeritPageController::class, 'index'])->name('merit.index');
    Route::get('/mt', [MtController::class, 'index'])->name('mt.index');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
    Route::post('/profile/photo', [ProfileController::class, 'photo'])->name('profile.photo');
    Route::delete('/profile/photo', [ProfileController::class, 'removePhoto'])->name('profile.photo.remove');

    Route::get('/push/key', [PushController::class, 'key'])->name('push.key');
    Route::post('/push/subscribe', [PushController::class, 'subscribe'])->middleware('throttle:20,1')->name('push.subscribe');
    Route::post('/push/unsubscribe', [PushController::class, 'unsubscribe'])->middleware('throttle:20,1')->name('push.unsubscribe');
    Route::post('/push/test', [PushController::class, 'test'])->middleware('throttle:5,1')->name('push.test');
});

// ---- Exco -----------------------------------------------------------------
Route::middleware(['auth', 'approved', 'can:view-mt', 'advisor.readonly'])->prefix('exco')->name('exco.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/members', [MemberController::class, 'index'])->name('members.index');
    Route::get('/members/export', [MemberController::class, 'export'])->name('members.export');
    Route::get('/members/{user}', [MemberController::class, 'show'])->name('members.show');

    Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::post('/approvals/bulk', [ApprovalController::class, 'bulk'])->name('approvals.bulk');
    Route::post('/approvals/{user}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::post('/approvals/{user}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');

    Route::get('/programs', [ProgramManageController::class, 'index'])->name('programs.index');
    Route::get('/programs/create', [ProgramManageController::class, 'create'])->name('programs.create');
    Route::post('/programs/map-link', [ProgramManageController::class, 'resolveMapLink'])->middleware('throttle:30,1')->name('programs.map-link');
    Route::post('/programs', [ProgramManageController::class, 'store'])->name('programs.store');
    Route::get('/programs/{program}/edit', [ProgramManageController::class, 'edit'])->name('programs.edit');
    Route::put('/programs/{program}', [ProgramManageController::class, 'update'])->name('programs.update');
    Route::post('/programs/{program}/cancel', [ProgramManageController::class, 'cancel'])->name('programs.cancel');
    Route::delete('/programs/{program}', [ProgramManageController::class, 'destroy'])->middleware('can:superadmin')->name('programs.destroy');

    Route::get('/emails', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('/emails/count', [AnnouncementController::class, 'count'])->name('announcements.count');
    Route::post('/emails', [AnnouncementController::class, 'store'])->middleware('throttle:6,1')->name('announcements.store');
    Route::post('/emails/image', [AnnouncementController::class, 'image'])->middleware('throttle:30,1')->name('announcements.image');
    Route::post('/emails/preview', [AnnouncementController::class, 'preview'])->name('announcements.preview');
    Route::post('/emails/{announcement}/next', [AnnouncementController::class, 'next'])->middleware('throttle:10,1')->name('announcements.next');
    Route::delete('/emails/{announcement}', [AnnouncementController::class, 'cancel'])->name('announcements.cancel');

    Route::post('/members/{user}/merit', [MeritController::class, 'store'])->name('merit.store');
    Route::delete('/merit/{entry}', [MeritController::class, 'destroy'])->name('merit.destroy');

    Route::get('/programs/{program}/attendance', [AttendanceController::class, 'show'])->name('attendance.show');
    Route::post('/programs/{program}/attendance', [AttendanceController::class, 'update'])->name('attendance.update');
    Route::post('/programs/{program}/attendance/absent', [AttendanceController::class, 'markRemainingAbsent'])->name('attendance.absent');
    Route::get('/programs/{program}/attendance/search', [AttendanceController::class, 'searchMembers'])->name('attendance.search');
    Route::post('/programs/{program}/checkin-toggle', [AttendanceController::class, 'toggleCheckin'])->name('attendance.checkin-toggle');
    Route::get('/programs/{program}/qr', [AttendanceController::class, 'qr'])->name('attendance.qr');
    Route::get('/programs/{program}/attendance/export', [AttendanceController::class, 'export'])->name('attendance.export');
    Route::get('/programs/{program}/report', [EventReportController::class, 'show'])->name('programs.report');
});

// ---- Admin ----------------------------------------------------------------
Route::middleware(['auth', 'approved', 'can:view-mt', 'advisor.readonly'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');
    Route::put('/settings/reminders', [SettingsController::class, 'reminders'])->name('settings.reminders');
    Route::put('/settings/merit', [SettingsController::class, 'merit'])->name('settings.merit');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings/social', [SettingsController::class, 'social'])->name('settings.social');
    Route::put('/settings/instagram', [SettingsController::class, 'instagram'])->name('settings.instagram');
    Route::post('/settings/instagram/sync', [SettingsController::class, 'syncInstagram'])->name('settings.instagram.sync');
    Route::post('/settings/pauses', [SettingsController::class, 'storePause'])->name('settings.pauses.store');
    Route::delete('/settings/pauses/{pause}', [SettingsController::class, 'destroyPause'])->name('settings.pauses.destroy');
    Route::post('/settings/recalculate', [SettingsController::class, 'recalculate'])->name('settings.recalculate');
    Route::post('/settings/database', [SettingsController::class, 'migrate'])->name('settings.database');
    Route::post('/settings/test-mail', [SettingsController::class, 'testMail'])->middleware('throttle:5,1')->name('settings.test-mail');
    Route::get('/settings/exports', [SettingsController::class, 'exports'])->name('settings.exports');
});

// ---- Superadmin -------------------------------------------------------------
Route::middleware(['auth', 'approved', 'can:superadmin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::put('/users/{user}/role', [UserController::class, 'role'])->name('users.role');
    Route::put('/users/{user}/position', [UserController::class, 'position'])->name('users.position');
    Route::post('/users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
    Route::post('/users/{user}/reinstate', [UserController::class, 'reinstate'])->name('users.reinstate');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::put('/mt/settings', [MtController::class, 'settings'])->name('mt.settings');
    Route::post('/mt/session', [MtController::class, 'session'])->name('mt.session');
});

// Activity log: the superadmin, and advisors (read-only).
Route::middleware(['auth', 'approved', 'can:view-activity'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/activity', [ActivityLogController::class, 'index'])->name('activity');
});
