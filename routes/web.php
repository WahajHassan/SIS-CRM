<?php

use App\Http\Controllers\SalesController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\WorkRecordController;
use App\Http\Controllers\AttendanceReportController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HrController;
use App\Http\Controllers\HrDashboardController;
use App\Http\Controllers\HrSettingsController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\PerformanceController;
use Illuminate\Support\Facades\Route;

// Root shows the first company's scoreboard. Pointed straight at the controller
// rather than a closure: closure routes cannot survive `route:cache`, and with a
// cached table "/" was being dispatched to SalesController@index with no
// argument, throwing ArgumentCountError (a 500 in production only).
Route::get('/', [SalesController::class, 'index'])->name('home');



// Public, and deliberately outside every gate: somebody deciding whether to use
// this CRM reads these before they have an account.
//
// The company's name is handed in rather than worked out inside the layout: a
// child view's sections are rendered before the layout runs, so anything the
// layout defines is not available to them.
foreach (['privacy' => 'legal.privacy', 'terms' => 'legal.terms'] as $path => $name) {
    Route::get('/' . $path, function () use ($name) {
        return view($name, [
            'companyName' => \App\Models\Company::withoutGlobalScopes()->value('name') ?: config('app.name'),
        ]);
    })->name($name);
}

// Auth Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Getting back in without waiting for an administrator to be awake. Guest only:
// somebody already signed in has no use for these and should not be able to
// walk somebody else through them.
Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [\App\Http\Controllers\PasswordResetController::class, 'askForm'])->name('password.request');
    Route::post('/forgot-password', [\App\Http\Controllers\PasswordResetController::class, 'sendLink'])->name('password.email');
    Route::get('/reset-password/{token}', [\App\Http\Controllers\PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [\App\Http\Controllers\PasswordResetController::class, 'reset'])->name('password.update');
});

// Serve files bypassing document root issues.
// The requested path is resolved and checked so it cannot climb out of public/.
Route::get('/file/{path}', function ($path) {
    $basePath = realpath(public_path());
    $fullPath = realpath(public_path($path));

    if ($basePath === false || $fullPath === false || !str_starts_with($fullPath, $basePath . DIRECTORY_SEPARATOR)) {
        abort(404);
    }

    $mimeType = \Illuminate\Support\Facades\File::mimeType($fullPath);
    return response()->file($fullPath, ['Content-Type' => $mimeType]);
})->where('path', '.*')->name('serve.file');

// Notepad. Personal to each signed-in user; NoteController scopes every query
// to auth()->id(), so these need only the auth middleware.
Route::middleware(['auth'])->prefix('notes')->name('notes.')->group(function () {
    Route::get('/', [NoteController::class, 'index'])->name('index');
    Route::post('/', [NoteController::class, 'store'])->name('store');
    Route::post('/reorder', [NoteController::class, 'reorder'])->name('reorder');
    Route::put('/{id}', [NoteController::class, 'update'])->name('update');
    Route::delete('/{id}', [NoteController::class, 'destroy'])->name('destroy');
});

// Chat Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/chat', [\App\Http\Controllers\ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/api/poll', [\App\Http\Controllers\ChatController::class, 'poll'])->name('chat.poll');
    Route::post('/chat/conversation/create', [\App\Http\Controllers\ChatController::class, 'storeConversation'])->name('chat.create');
    Route::get('/chat/{conversation}', [\App\Http\Controllers\ChatController::class, 'fetchMessages'])->name('chat.fetch');
    Route::post('/chat/{conversation}', [\App\Http\Controllers\ChatController::class, 'sendMessage'])->name('chat.send');
    Route::put('/chat/conversation/{conversation}', [\App\Http\Controllers\ChatController::class, 'updateConversation'])->name('chat.update');
    Route::delete('/chat/conversation/{conversation}', [\App\Http\Controllers\ChatController::class, 'deleteConversation'])->name('chat.delete');
    Route::post('/chat/conversation/{conversation}/leave', [\App\Http\Controllers\ChatController::class, 'leaveConversation'])->name('chat.leave');
    Route::get('/chat/api/presence', [\App\Http\Controllers\ChatController::class, 'presence'])->name('chat.presence');
    // Message-level actions: the controller checks ownership (or admin, for
    // delete) on each one, so membership of the conversation is not enough.
    Route::put('/chat/message/{id}', [\App\Http\Controllers\ChatController::class, 'editMessage'])->name('chat.message.edit');
    Route::delete('/chat/message/{id}', [\App\Http\Controllers\ChatController::class, 'deleteMessage'])->name('chat.message.delete');
    Route::post('/chat/message/{id}/react', [\App\Http\Controllers\ChatController::class, 'toggleReaction'])->name('chat.message.react');
    Route::post('/chat/typing/{conversation}', [\App\Http\Controllers\ChatController::class, 'typing'])->name('chat.typing');
});

// Protected Admin Dashboard Routes
Route::middleware(['auth'])->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    
    // Companies Management (Superadmin Only)
    Route::get('/companies', [AdminController::class, 'companies'])->name('admin.companies');
    Route::post('/companies', [AdminController::class, 'storeCompany'])->name('admin.companies.store');
    Route::put('/companies/{id}', [AdminController::class, 'updateCompany'])->name('admin.companies.update');
    Route::delete('/companies/{id}', [AdminController::class, 'deleteCompany'])->name('admin.companies.delete');
    
    // Attendance Routes
    Route::get('/attendance/clock-in', function() { return redirect()->route('admin.dashboard'); });
    Route::post('/attendance/clock-in', [\App\Http\Controllers\AttendanceController::class, 'clockIn'])->name('admin.attendance.clock-in');
    
    Route::get('/attendance/start-break', function() { return redirect()->route('admin.dashboard'); });
    Route::post('/attendance/start-break', [\App\Http\Controllers\AttendanceController::class, 'startBreak'])->name('admin.attendance.start-break');
    
    Route::get('/attendance/start-global-break', function() { return redirect()->route('admin.dashboard'); });
    Route::post('/attendance/start-global-break', [\App\Http\Controllers\AttendanceController::class, 'startGlobalBreak'])->name('admin.attendance.start-global-break');
    
    Route::get('/attendance/pause-global-break', function() { return redirect()->route('admin.dashboard'); });
    Route::post('/attendance/pause-global-break', [\App\Http\Controllers\AttendanceController::class, 'pauseGlobalBreak'])->name('admin.attendance.pause-global-break');
    
    Route::get('/attendance/resume-global-break', function() { return redirect()->route('admin.dashboard'); });
    Route::post('/attendance/resume-global-break', [\App\Http\Controllers\AttendanceController::class, 'resumeGlobalBreak'])->name('admin.attendance.resume-global-break');
    
    Route::get('/attendance/force-end-break/{userId}', function() { return redirect()->route('admin.dashboard'); });
    Route::post('/attendance/force-end-break/{userId}', [\App\Http\Controllers\AttendanceController::class, 'forceEndBreak'])->name('admin.attendance.force-end-break');
    
    Route::get('/attendance/force-start-main-break/{userId}', function() { return redirect()->route('admin.dashboard'); });
    Route::post('/attendance/force-start-main-break/{userId}', [\App\Http\Controllers\AttendanceController::class, 'forceStartMainBreak'])->name('admin.attendance.force-start-main-break');
    
    Route::get('/attendance/end-global-break', function() { return redirect()->route('admin.dashboard'); });
    Route::post('/attendance/end-global-break', [\App\Http\Controllers\AttendanceController::class, 'endGlobalBreak'])->name('admin.attendance.end-global-break');
    
    Route::get('/attendance/reset-global-break', function() { return redirect()->route('admin.dashboard'); });
    Route::post('/attendance/reset-global-break', [\App\Http\Controllers\AttendanceController::class, 'resetGlobalBreak'])->name('admin.attendance.reset-global-break');
    
    Route::get('/attendance/end-break', function() { return redirect()->route('admin.dashboard'); });
    Route::post('/attendance/end-break', [\App\Http\Controllers\AttendanceController::class, 'endBreak'])->name('admin.attendance.end-break');
    
    Route::get('/attendance/clock-out', function() { return redirect()->route('admin.dashboard'); });
    Route::post('/attendance/clock-out', [\App\Http\Controllers\AttendanceController::class, 'clockOut'])->name('admin.attendance.clock-out');
    Route::get('/attendance', [\App\Http\Controllers\AttendanceController::class, 'index'])->name('admin.attendance');
    Route::get('/attendance/export', [\App\Http\Controllers\AttendanceController::class, 'exportCsv'])->name('admin.attendance.export');
    // Salary is part of the attendance section, not a section of its own.
    Route::post('/attendance/salary-rules', [\App\Http\Controllers\AttendanceController::class, 'updatePayrollSettings'])->name('admin.attendance.salary-rules');
    Route::post('/attendance/salary/{userId}', [\App\Http\Controllers\AttendanceController::class, 'updateSalary'])->name('admin.attendance.salary.update');
    Route::get('/attendance/salary-export', [\App\Http\Controllers\AttendanceController::class, 'exportPayroll'])->name('admin.attendance.salary.export');
    Route::get('/attendance/print', [\App\Http\Controllers\AttendanceController::class, 'printReport'])->name('admin.attendance.print');
    
    // Overtimes
    Route::get('/attendance/active-overtimes', [\App\Http\Controllers\AttendanceController::class, 'getActiveOvertimes'])->name('admin.attendance.active-overtimes');

    // Global Breaks. The five actions are declared further up as
    // /attendance/start-global-break and so on. A second copy of them lived here
    // under /attendance/global-break/start with the same route names, which stopped
    // "php artisan route:cache" working at all: Laravel refuses to serialise two
    // routes sharing a name. Nothing linked to these paths, so they are gone.
    Route::get('/attendance/global-breaks-control', [\App\Http\Controllers\AttendanceController::class, 'getGlobalBreaksControlHtml'])->name('admin.attendance.global-breaks-control');

    // Break Appeal Requests
    Route::post('/attendance/appeal-break', [\App\Http\Controllers\AttendanceController::class, 'appealBreak'])->name('admin.attendance.appeal-break');
    Route::get('/attendance/appeal-status', [\App\Http\Controllers\AttendanceController::class, 'appealStatus'])->name('admin.attendance.appeal-status');
    Route::get('/attendance/pending-appeals', [\App\Http\Controllers\AttendanceController::class, 'getPendingAppeals'])->name('admin.attendance.pending-appeals');
    Route::post('/attendance/appeal/{id}/approve', [\App\Http\Controllers\AttendanceController::class, 'approveAppeal'])->name('admin.attendance.appeal.approve');
    Route::post('/attendance/appeal/{id}/decline', [\App\Http\Controllers\AttendanceController::class, 'declineAppeal'])->name('admin.attendance.appeal.decline');
    
    // Agent Profile Routes
    Route::get('/my-profile', [AdminController::class, 'myProfile'])->name('admin.my_profile');
    Route::put('/my-profile', [AdminController::class, 'updateMyProfile'])->name('admin.my_profile.update');
    Route::get('/team', [AdminController::class, 'team'])->name('admin.team');
    Route::get('/history', [AdminController::class, 'history'])->name('admin.history');
    Route::get('/history/agent', [AdminController::class, 'agentHistory'])->name('admin.history.agent');
    Route::get('/deals', [AdminController::class, 'deals'])->name('admin.deals');
    Route::get('/logs', [AdminController::class, 'logs'])->name('admin.logs');
    Route::get('/global-settings', [AdminController::class, 'globalSettings'])->name('admin.settings.global');
    Route::post('/global-settings', [AdminController::class, 'updateGlobalSettings'])->name('admin.settings.global.update');
    Route::get('/settings', [AdminController::class, 'settings'])->name('admin.settings');
    Route::post('/settings/branding', [AdminController::class, 'updateBranding'])->name('admin.settings.branding.update');
    Route::post('/settings/password', [AdminController::class, 'updatePassword'])->name('admin.password.update');
    
    // Rush Hour
    Route::post('/rush-hour/start', [AdminController::class, 'startRushHour'])->name('admin.rush_hour.start');
    Route::post('/rush-hour/stop', [AdminController::class, 'stopRushHour'])->name('admin.rush_hour.stop');
    
    // Notifications
    Route::post('/notifications/send', [AdminController::class, 'sendNotification'])->name('admin.notifications.send');
    Route::get('/notifications/fetch', [\App\Http\Controllers\NotificationController::class, 'fetch'])->name('notifications.fetch');
    Route::post('/notifications/read/{id}', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.read_all');
    Route::delete('/notifications/{id}', [\App\Http\Controllers\NotificationController::class, 'deleteGlobal'])->name('notifications.delete');
    
    // Updates & CRUD
    Route::post('/stats/update', [AdminController::class, 'updateStats'])->name('admin.stats.update');
    Route::post('/performers', [AdminController::class, 'storePerformer'])->name('admin.performers.store');
    Route::put('/performers/{id}', [AdminController::class, 'updatePerformer'])->name('admin.performers.update');
    Route::delete('/performers/{id}', [AdminController::class, 'deletePerformer'])->name('admin.performers.delete');
    Route::delete('/performers/{id}/force', [AdminController::class, 'forceDeletePerformer'])->name('admin.performers.force-delete');
    Route::post('/performers/{id}/toggle-closer', [AdminController::class, 'toggleCloser'])->name('admin.performers.toggle-closer');
    
    Route::post('/deals', [AdminController::class, 'storeDeal'])->name('admin.deals.store');
    Route::put('/deals/{id}', [AdminController::class, 'updateDeal'])->name('admin.deals.update');
    Route::delete('/deals/{id}', [AdminController::class, 'deleteDeal'])->name('admin.deals.delete');
    
    Route::get('/leads', [AdminController::class, 'leads'])->name('admin.leads');
    Route::post('/leads', [AdminController::class, 'storeLead'])->name('admin.leads.store');
    
    Route::get('/transferred-leads', [AdminController::class, 'transferredLeads'])->name('admin.transferred_leads');
    Route::post('/transferred-leads', [AdminController::class, 'storeTransferredLead'])->name('admin.transferred_leads.store');
    Route::put('/transferred-leads/{id}', [AdminController::class, 'updateTransferredLead'])->name('admin.transferred_leads.update');
    Route::delete('/transferred-leads/{id}', [AdminController::class, 'deleteTransferredLead'])->name('admin.transferred_leads.delete');
    
    // New routes for Transfer Leads features
    Route::get('/transferred-leads-reminders', [AdminController::class, 'checkReminders'])->name('admin.transferred_leads.reminders');
    Route::post('/transferred-leads/{id}/proposal-sent', [AdminController::class, 'markProposalSent'])->name('admin.transferred_leads.proposal_sent');
    Route::post('/transferred-leads/{id}/dismiss-15m', [AdminController::class, 'dismiss15m'])->name('admin.transferred_leads.dismiss_15m');
    Route::post('/transferred-leads/{id}/convert', [AdminController::class, 'toggleConversion'])->name('admin.transferred_leads.convert');

    // The pipeline: take one out of the pool, put it back, move it along.
    Route::post('/transferred-leads/{id}/claim', [AdminController::class, 'claimLead'])->name('admin.transferred_leads.claim');
    Route::post('/transferred-leads/{id}/release', [AdminController::class, 'releaseLead'])->name('admin.transferred_leads.release');
    Route::post('/transferred-leads/{id}/stage', [AdminController::class, 'moveLeadStage'])->name('admin.transferred_leads.stage');
    
    Route::put('/leads/{id}', [AdminController::class, 'updateLead'])->name('admin.leads.update');

    // Viewrec: the free signups the floor is asked for five of a day.
    Route::get('/viewrec', [\App\Http\Controllers\FreeSignupController::class, 'index'])->name('admin.viewrec');
    Route::post('/viewrec', [\App\Http\Controllers\FreeSignupController::class, 'store'])->name('admin.viewrec.store');
    Route::get('/viewrec/export', [\App\Http\Controllers\FreeSignupController::class, 'export'])->name('admin.viewrec.export');
    Route::put('/viewrec/{id}/status', [\App\Http\Controllers\FreeSignupController::class, 'updateStatus'])->name('admin.viewrec.status');
    Route::delete('/viewrec/{id}', [\App\Http\Controllers\FreeSignupController::class, 'destroy'])->name('admin.viewrec.destroy');

    // A fingerprint or a face at the clock. Everybody manages their own, so
    // these sit with the other things a person does for themselves.
    Route::get('/devices', [\App\Http\Controllers\DeviceKeyController::class, 'index'])->name('admin.devices');
    Route::post('/devices/setup/begin', [\App\Http\Controllers\DeviceKeyController::class, 'beginSetup'])->name('admin.devices.setup.begin');
    Route::post('/devices/setup/finish', [\App\Http\Controllers\DeviceKeyController::class, 'finishSetup'])->name('admin.devices.setup.finish');
    Route::delete('/devices/{device}', [\App\Http\Controllers\DeviceKeyController::class, 'forget'])->name('admin.devices.forget');
    Route::post('/devices/check/begin', [\App\Http\Controllers\DeviceKeyController::class, 'beginCheck'])->name('admin.devices.check.begin');
    Route::post('/devices/check/finish', [\App\Http\Controllers\DeviceKeyController::class, 'finishCheck'])->name('admin.devices.check.finish');

    // What somebody has earned, read off their own attendance and their own sales.
    Route::get('/wallet', [\App\Http\Controllers\WalletController::class, 'index'])->name('admin.wallet');

    // What the floor gets called out for: rush hour, a break, prayer time.
    Route::get('/moments', [\App\Http\Controllers\FloorMomentController::class, 'index'])->name('admin.moments');
    Route::post('/moments/{moment}/start', [\App\Http\Controllers\FloorMomentController::class, 'start'])->name('admin.moments.start');
    Route::post('/moments/stop', [\App\Http\Controllers\FloorMomentController::class, 'stop'])->name('admin.moments.stop');
    Route::get('/settings/moments', [\App\Http\Controllers\FloorMomentController::class, 'settings'])->name('admin.moments.settings');
    Route::post('/settings/moments', [\App\Http\Controllers\FloorMomentController::class, 'store'])->name('admin.moments.store');
    Route::put('/settings/moments/{moment}', [\App\Http\Controllers\FloorMomentController::class, 'update'])->name('admin.moments.update');
    Route::delete('/settings/moments/{moment}', [\App\Http\Controllers\FloorMomentController::class, 'destroy'])->name('admin.moments.destroy');

    // The break room: something to watch, and something to play.
    Route::get('/netflix', [\App\Http\Controllers\BreakRoomController::class, 'watch'])->name('admin.netflix');
    Route::get('/games', [\App\Http\Controllers\BreakRoomController::class, 'games'])->name('admin.games');
    Route::post('/games/score', [\App\Http\Controllers\BreakRoomController::class, 'score'])->name('admin.games.score');

    // Quick Huddle: one person looking at another's CRM, with permission.
    // The pending-request check is reachable from every screen, because a
    // request has to find the host wherever they are.
    Route::get('/huddle', [\App\Http\Controllers\HuddleController::class, 'index'])->name('admin.huddle');
    Route::get('/huddle/people', [\App\Http\Controllers\HuddleController::class, 'people'])->name('admin.huddle.people');
    Route::post('/huddle/request', [\App\Http\Controllers\HuddleController::class, 'request'])->name('admin.huddle.request');
    Route::get('/huddle/mine', [\App\Http\Controllers\HuddleController::class, 'mine'])->name('admin.huddle.mine');
    Route::get('/huddle/{huddle}/share', [\App\Http\Controllers\HuddleController::class, 'share'])->name('admin.huddle.share');
    Route::post('/huddle/{huddle}/answer', [\App\Http\Controllers\HuddleController::class, 'answer'])->name('admin.huddle.answer');
    Route::post('/huddle/{huddle}/control', [\App\Http\Controllers\HuddleController::class, 'control'])->name('admin.huddle.control');
    Route::post('/huddle/{huddle}/end', [\App\Http\Controllers\HuddleController::class, 'end'])->name('admin.huddle.end');
    Route::post('/huddle/{huddle}/signal', [\App\Http\Controllers\HuddleController::class, 'signal'])->name('admin.huddle.signal');
    Route::get('/huddle/{huddle}/signals', [\App\Http\Controllers\HuddleController::class, 'signals'])->name('admin.huddle.signals');

    // Training: what the floor is taught, and what they ask about it.
    Route::get('/training', [\App\Http\Controllers\TrainingController::class, 'index'])->name('admin.training');
    Route::post('/training', [\App\Http\Controllers\TrainingController::class, 'store'])->name('admin.training.store');
    Route::post('/training/chunk', [\App\Http\Controllers\TrainingController::class, 'chunk'])->name('admin.training.chunk');
    Route::get('/training/{id}', [\App\Http\Controllers\TrainingController::class, 'show'])->name('admin.training.show');
    Route::delete('/training/{id}', [\App\Http\Controllers\TrainingController::class, 'destroy'])->name('admin.training.destroy');
    Route::get('/training/{id}/comments', [\App\Http\Controllers\TrainingController::class, 'comments'])->name('admin.training.comments');
    Route::post('/training/{id}/comments', [\App\Http\Controllers\TrainingController::class, 'comment'])->name('admin.training.comment');
    Route::delete('/training/comments/{id}', [\App\Http\Controllers\TrainingController::class, 'removeComment'])->name('admin.training.comment.remove');
    Route::get('/training/{id}/watchers', [\App\Http\Controllers\TrainingController::class, 'watchers'])->name('admin.training.watchers');

    // The cities of a state, for the dropdown that follows the state.
    Route::get('/states/{state}/cities', [AdminController::class, 'citiesOfState'])->name('admin.states.cities');
    Route::delete('/leads/{id}', [AdminController::class, 'deleteLead'])->name('admin.leads.delete');
    
    // News Headlines
    Route::get('/headlines', [AdminController::class, 'headlines'])->name('admin.headlines');
    Route::post('/headlines', [AdminController::class, 'storeHeadline'])->name('admin.headlines.store');
    Route::put('/headlines/{id}', [AdminController::class, 'updateHeadline'])->name('admin.headlines.update');
    Route::post('/headlines/{id}/toggle', [AdminController::class, 'toggleHeadline'])->name('admin.headlines.toggle');
    Route::delete('/headlines/{id}', [AdminController::class, 'deleteHeadline'])->name('admin.headlines.delete');
    
    // Business Categories
    Route::get('/categories', [AdminController::class, 'categories'])->name('admin.categories');
    Route::post('/categories', [AdminController::class, 'storeCategory'])->name('admin.categories.store');
    Route::put('/categories/{id}', [AdminController::class, 'updateCategory'])->name('admin.categories.update');
    Route::post('/categories/{id}/toggle', [AdminController::class, 'toggleCategory'])->name('admin.categories.toggle');
    Route::delete('/categories/{id}', [AdminController::class, 'deleteCategory'])->name('admin.categories.delete');
    
    // USA States
    Route::get('/usa-states', [AdminController::class, 'usaStates'])->name('admin.usa_states');
    Route::post('/usa-states', [AdminController::class, 'storeUsaState'])->name('admin.usa_states.store');
    Route::put('/usa-states/{id}', [AdminController::class, 'updateUsaState'])->name('admin.usa_states.update');
    Route::post('/usa-states/{id}/toggle', [AdminController::class, 'toggleUsaState'])->name('admin.usa_states.toggle');
    Route::delete('/usa-states/{id}', [AdminController::class, 'deleteUsaState'])->name('admin.usa_states.delete');
    
    // User Management
    Route::get('/impersonate/leave', [AdminController::class, 'leaveImpersonate'])->name('admin.impersonate.leave');
    Route::get('/impersonate/{id}', [AdminController::class, 'impersonate'])->name('admin.impersonate');
    Route::get('/users', [AdminController::class, 'users'])->name('admin.users');
    Route::post('/users', [AdminController::class, 'storeUser'])->name('admin.users.store');
    Route::put('/users/{id}', [AdminController::class, 'updateUserRole'])->name('admin.users.update');
    Route::put('/users/{id}/password', [AdminController::class, 'updateUserPassword'])->name('admin.users.password.update');
    Route::delete('/users/{id}', [AdminController::class, 'deleteUser'])->name('admin.users.delete');
    Route::delete('/users/{id}/login-history', [AdminController::class, 'deleteLoginHistory'])->name('admin.users.login_history.delete');
    Route::post('/users/{id}/approve', [AdminController::class, 'approveUser'])->name('admin.users.approve');
    Route::post('/users/{id}/verify', [AdminController::class, 'toggleVerified'])->name('admin.users.verify');

    // Where deleted things wait. Restore is ordinary; purge is the owner's
    // alone and is the only real DELETE left in the application.
    // Getting the work back out. Each one runs the same query as the screen it
    // sits on, so the file matches what the person is looking at.
    Route::get('/leads/export', [\App\Http\Controllers\ExportController::class, 'leads'])->name('admin.leads.export');
    Route::post('/leads/import', [AdminController::class, 'importLeads'])->name('admin.leads.import');

    // A payslip somebody can keep. Your own always; anybody else's only with the
    // HR or payroll screens — the controller decides, not the menu.
    Route::get('/payslip/{user}', [\App\Http\Controllers\PayslipController::class, 'show'])->name('admin.payslip');
    Route::get('/deals/export', [\App\Http\Controllers\ExportController::class, 'deals'])->name('admin.deals.export');
    Route::get('/transferred-leads/export', [\App\Http\Controllers\ExportController::class, 'transfers'])->name('admin.transferred_leads.export');

    // One place to look for anything. The controller decides what each person
    // is allowed to find, so this needs no gate of its own.
    Route::get('/search', \App\Http\Controllers\SearchController::class)->name('admin.search');

    Route::get('/recycle-bin', [\App\Http\Controllers\RecycleBinController::class, 'index'])->name('admin.bin');
    Route::post('/recycle-bin/{kind}/{id}/restore', [\App\Http\Controllers\RecycleBinController::class, 'restore'])->name('admin.bin.restore');
    Route::post('/recycle-bin/{kind}/{id}/purge', [\App\Http\Controllers\RecycleBinController::class, 'purge'])->name('admin.bin.purge');

    // Support Routes
    Route::get('/support', [\App\Http\Controllers\SupportController::class, 'dashboard'])->name('support.dashboard');
    Route::get('/support/clients/{type}', [\App\Http\Controllers\SupportController::class, 'clients'])->name('support.clients');
    Route::get('/support/clients/profile/{id}', [\App\Http\Controllers\SupportController::class, 'clientProfile'])->name('support.client.profile');
    Route::put('/support/deals/{id}/status', [\App\Http\Controllers\SupportController::class, 'updateDealStatus'])->name('support.updateDealStatus');
    Route::get('/support/sheet', [\App\Http\Controllers\SupportController::class, 'sheetView'])->name('support.sheet');
    Route::post('/support/sheet/save', [\App\Http\Controllers\SupportController::class, 'saveSheetData'])->name('support.sheet.save');
    Route::get('/support/tickets/{id}', [\App\Http\Controllers\SupportController::class, 'show'])->name('support.show');
    Route::put('/support/tickets/{id}/status', [\App\Http\Controllers\SupportController::class, 'updateStatus'])->name('support.updateStatus');
    Route::post('/support/tickets/{id}/note', [\App\Http\Controllers\SupportController::class, 'addNote'])->name('support.addNote');
    Route::put('/support/tickets/{id}/client', [\App\Http\Controllers\SupportController::class, 'updateClient'])->name('support.updateClient');

    // Email Templates
    Route::get('/email-templates', [\App\Http\Controllers\EmailTemplateController::class, 'index'])->name('admin.email_templates');
    Route::post('/email-templates', [\App\Http\Controllers\EmailTemplateController::class, 'store'])->name('admin.email_templates.store');
    Route::put('/email-templates/{id}', [\App\Http\Controllers\EmailTemplateController::class, 'update'])->name('admin.email_templates.update');
    Route::delete('/email-templates/{id}', [\App\Http\Controllers\EmailTemplateController::class, 'destroy'])->name('admin.email_templates.delete');
    Route::get('/email-templates/api/all', [\App\Http\Controllers\EmailTemplateController::class, 'getAll'])->name('admin.email_templates.api');
    Route::post('/rich-editor/upload-image', [\App\Http\Controllers\EmailTemplateController::class, 'uploadImage'])->name('admin.rich_editor.upload');

    // Emails (Send & Inbox)
    Route::get('/emails', [\App\Http\Controllers\EmailController::class, 'inbox'])->name('admin.emails.inbox');
    Route::post('/emails/send', [\App\Http\Controllers\EmailController::class, 'send'])->name('admin.emails.send');
    Route::get('/emails/thread/{type}/{id}', [\App\Http\Controllers\EmailController::class, 'getThread'])->name('admin.emails.thread');

    // E-Signatures & DocuSign
    Route::post('/signatures/docusign/send', [\App\Http\Controllers\DocuSignController::class, 'sendEnvelope'])->name('admin.docusign.send');
    Route::get('/signatures/docusign/callback', [\App\Http\Controllers\DocuSignController::class, 'callback'])->name('admin.docusign.callback');
    
    // Built-in Signature (Public route for clients to sign)
    Route::get('/sign/{type}/{id}', [\App\Http\Controllers\SignatureController::class, 'showSignPage'])->name('signature.sign_page')->withoutMiddleware(['auth']);
    Route::post('/sign/{type}/{id}/save', [\App\Http\Controllers\SignatureController::class, 'saveSignature'])->name('signature.save')->withoutMiddleware(['auth']);

    /*
    | HR. Employee records, leave and targets. Access is decided by the switches
    | in config/permissions.php like every other section; the two "my own" pages
    | are listed there as always allowed so any employee can use them.
    */
    Route::get('/hr/employees', [HrController::class, 'index'])->name('admin.hr.employees');
    Route::get('/hr/employees/export', [HrController::class, 'exportCsv'])->name('admin.hr.employees.export');
    Route::post('/hr/employees', [HrController::class, 'storeEmployee'])->name('admin.hr.employees.store');
    Route::post('/hr/employees/{user}/attendance', [HrController::class, 'saveAttendance'])->name('admin.hr.employee.attendance');
    Route::delete('/hr/employees/{user}/breaks/{break}', [HrController::class, 'deleteBreak'])->name('admin.hr.employee.break.delete');
    Route::get('/hr/employees/{user}', [HrController::class, 'show'])->name('admin.hr.employee')->where('user', '[0-9]+');
    Route::post('/hr/employees/{user}', [HrController::class, 'saveProfile'])->name('admin.hr.employee.save')->where('user', '[0-9]+');
    Route::post('/hr/employees/{user}/documents', [HrController::class, 'uploadDocument'])->name('admin.hr.employee.document');
    Route::delete('/hr/employees/{user}/documents/{document}', [HrController::class, 'deleteDocument'])->name('admin.hr.employee.document.delete');
    Route::post('/hr/employees/{user}/remove-file', [HrController::class, 'deleteProfileFile'])->name('admin.hr.employee.file.delete');

    // The HR dashboard, and an employee's own month.
    Route::get('/hr/dashboard', [HrDashboardController::class, 'index'])->name('admin.hr.dashboard');
    Route::get('/my-attendance', [HrDashboardController::class, 'myAttendance'])->name('admin.hr.my_attendance');

    // The board on the dashboard. Reading it is for everybody; posting is
    // checked in the controller, which is why it is not a section of its own.
    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('admin.announcements.index');
    Route::post('/announcements', [AnnouncementController::class, 'store'])->name('admin.announcements.store');
    Route::delete('/announcements/{id}', [AnnouncementController::class, 'destroy'])->name('admin.announcements.delete');

    // Bonuses and achievements, which HR edits alongside the other rules.
    Route::post('/hr/settings/incentives', [HrSettingsController::class, 'storeIncentive'])->name('admin.hr.settings.incentives.store');
    Route::put('/hr/settings/incentives/{id}', [HrSettingsController::class, 'updateIncentive'])->name('admin.hr.settings.incentives.update');
    Route::delete('/hr/settings/incentives/{id}', [HrSettingsController::class, 'destroyIncentive'])->name('admin.hr.settings.incentives.delete');

    // What each person actually did: transfers, deals, tickets, days in.
    Route::get('/hr/work-record', [WorkRecordController::class, 'index'])->name('admin.hr.work_record');
    Route::get('/hr/work-record/export', [WorkRecordController::class, 'export'])->name('admin.hr.work_record.export');

    // Reports: the month as a file, and the saved shapes of that file.
    Route::get('/hr/reports', [AttendanceReportController::class, 'index'])->name('admin.hr.reports');
    Route::post('/hr/reports/download', [AttendanceReportController::class, 'download'])->name('admin.hr.reports.download');
    Route::post('/hr/reports/presets', [AttendanceReportController::class, 'storePreset'])->name('admin.hr.reports.preset');
    Route::delete('/hr/reports/presets/{id}', [AttendanceReportController::class, 'destroyPreset'])->name('admin.hr.reports.preset.delete');

    // HR settings: the rules, shifts, departments, holidays and one-off working
    // days that the attendance and leave code reads instead of hard-coding.
    Route::get('/hr/settings', [HrSettingsController::class, 'index'])->name('admin.hr.settings');
    Route::post('/hr/settings/rules', [HrSettingsController::class, 'updateRules'])->name('admin.hr.settings.rules');
    Route::post('/hr/settings/shifts', [HrSettingsController::class, 'storeShift'])->name('admin.hr.settings.shifts.store');
    Route::put('/hr/settings/shifts/{id}', [HrSettingsController::class, 'updateShift'])->name('admin.hr.settings.shifts.update');
    Route::delete('/hr/settings/shifts/{id}', [HrSettingsController::class, 'destroyShift'])->name('admin.hr.settings.shifts.delete');
    Route::post('/hr/settings/shift-assignment', [HrSettingsController::class, 'assignShift'])->name('admin.hr.settings.shifts.assign');
    Route::post('/hr/settings/departments', [HrSettingsController::class, 'storeDepartment'])->name('admin.hr.settings.departments.store');
    Route::put('/hr/settings/departments/{id}', [HrSettingsController::class, 'updateDepartment'])->name('admin.hr.settings.departments.update');
    Route::delete('/hr/settings/departments/{id}', [HrSettingsController::class, 'destroyDepartment'])->name('admin.hr.settings.departments.delete');
    Route::post('/hr/settings/holidays', [HrSettingsController::class, 'storeHoliday'])->name('admin.hr.settings.holidays.store');
    Route::put('/hr/settings/holidays/{id}', [HrSettingsController::class, 'updateHoliday'])->name('admin.hr.settings.holidays.update');
    Route::delete('/hr/settings/holidays/{id}', [HrSettingsController::class, 'destroyHoliday'])->name('admin.hr.settings.holidays.delete');
    Route::post('/hr/settings/import', [HrSettingsController::class, 'importAttendance'])->name('admin.hr.settings.import');
    Route::post('/hr/settings/overrides', [HrSettingsController::class, 'storeOverride'])->name('admin.hr.settings.overrides.store');
    Route::delete('/hr/settings/overrides/{id}', [HrSettingsController::class, 'destroyOverride'])->name('admin.hr.settings.overrides.delete');

    Route::get('/hr/leaves', [LeaveController::class, 'index'])->name('admin.hr.leaves');
    Route::post('/hr/leaves', [LeaveController::class, 'store'])->name('admin.hr.leaves.store');
    Route::post('/hr/leaves/{id}/decide', [LeaveController::class, 'decide'])->name('admin.hr.leaves.decide');
    Route::post('/hr/leaves/{id}/cancel', [LeaveController::class, 'cancel'])->name('admin.hr.leaves.cancel');
    Route::delete('/hr/leaves/{id}', [LeaveController::class, 'destroy'])->name('admin.hr.leaves.destroy');
    Route::post('/hr/leave-types', [LeaveController::class, 'storeType'])->name('admin.hr.leave_types.store');
    Route::put('/hr/leave-types/{id}', [LeaveController::class, 'updateType'])->name('admin.hr.leave_types.update');
    Route::delete('/hr/leave-types/{id}', [LeaveController::class, 'destroyType'])->name('admin.hr.leave_types.delete');
    Route::get('/my-leaves', [LeaveController::class, 'mine'])->name('admin.hr.my_leaves');

    Route::get('/hr/performance', [PerformanceController::class, 'index'])->name('admin.hr.performance');
    Route::post('/hr/performance/{user}/jd', [PerformanceController::class, 'saveJd'])->name('admin.hr.jd.save');
    Route::post('/hr/performance/{user}/kpi', [PerformanceController::class, 'storeKpi'])->name('admin.hr.kpi.store');
    Route::put('/hr/performance/kpi/{id}', [PerformanceController::class, 'updateKpi'])->name('admin.hr.kpi.update');
    Route::delete('/hr/performance/kpi/{id}', [PerformanceController::class, 'destroyKpi'])->name('admin.hr.kpi.delete');
    Route::get('/my-targets', [PerformanceController::class, 'mine'])->name('admin.hr.my_performance');

});

// Fallback route to serve storage files on shared hosting without symlinks.
// Same containment check as the /file route above.
Route::get('/storage/{path}', function ($path) {
    $basePath = realpath(storage_path('app/public'));
    $filePath = realpath(storage_path('app/public/' . $path));

    if ($basePath === false || $filePath === false || !str_starts_with($filePath, $basePath . DIRECTORY_SEPARATOR)) {
        abort(404);
    }

    return response()->file($filePath);
})->where('path', '.*')->name('storage.fallback');

// Scoreboard routes (defined last among public root routes to avoid conflict with /login, /logout, /admin, etc.)
// A company slug must be at least one character. Without this constraint the
// compiled matcher that `route:cache` switches to lets /{company_slug} match the
// empty string, so "/" resolves to SalesController@index with no argument and
// throws ArgumentCountError instead of hitting the root route above. Only shows
// up once routes are cached, which is production only.
Route::get('/{company_slug}/rush-hour/status', [\App\Http\Controllers\AdminController::class, 'rushHourStatus'])->name('scoreboard.rush-hour.status')->where('company_slug', '[A-Za-z0-9_-]+');
Route::get('/{company_slug}', [SalesController::class, 'index'])->name('scoreboard')->where('company_slug', '[A-Za-z0-9_-]+');
