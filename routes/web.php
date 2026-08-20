<?php

use App\Http\Controllers\Admin\AddonController as AdminAddonController;
use App\Http\Controllers\Admin\AppointmentCalendarController as AdminAppointmentCalendarController;
use App\Http\Controllers\Admin\AppointmentController as AdminAppointmentController;
use App\Http\Controllers\Admin\CommissionController as AdminCommissionController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Admin\PromotionController as AdminPromotionController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Admin\StaffController as AdminStaffController;
use App\Http\Controllers\Admin\StaffScheduleExceptionController as AdminStaffScheduleExceptionController;
use App\Http\Controllers\Admin\StaffWeeklyScheduleController as AdminStaffWeeklyScheduleController;
use App\Http\Controllers\Admin\SystemErrorReportController as AdminSystemErrorReportController;
use App\Http\Controllers\Admin\TransactionController as AdminTransactionController;
use App\Http\Controllers\Admin\UserManagementController as AdminUserManagementController;
use App\Http\Controllers\Admin\WeeklyRosterController as AdminWeeklyRosterController;
use App\Http\Controllers\Auth\GoogleDeletionController;
use App\Http\Controllers\Auth\GooglePasswordSetupController;
use App\Http\Controllers\Customer\AppointmentCalendarController as CustomerAppointmentCalendarController;
use App\Http\Controllers\Customer\AppointmentController as CustomerAppointmentController;
use App\Http\Controllers\Customer\FeedbackController as CustomerFeedbackController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Reception\AppointmentCalendarController as ReceptionAppointmentCalendarController;
use App\Http\Controllers\Reception\AppointmentController as ReceptionAppointmentController;
use App\Http\Controllers\Reception\CustomerController as ReceptionCustomerController;
use App\Http\Controllers\Reception\DashboardController as ReceptionDashboardController;
use App\Http\Controllers\Reception\TransactionController as ReceptionTransactionController;
use App\Http\Controllers\Staff\AppointmentCalendarController as StaffAppointmentCalendarController;
use App\Http\Controllers\Staff\AppointmentController as StaffAppointmentController;
use App\Http\Controllers\Staff\CommissionController as StaffCommissionController;
use App\Http\Controllers\Staff\CustomerController as StaffCustomerController;
use App\Http\Controllers\Staff\DashboardController as StaffDashboardController;
use App\Http\Controllers\Staff\FeedbackController as StaffFeedbackController;
use App\Http\Controllers\Staff\TransactionController as StaffTransactionController;
use App\Http\Controllers\WebAttendanceController;
use App\Http\Middleware\MeasureApplicationRequest;
use App\Models\Addon;
use App\Models\ApplicationSetting;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $activeServices = Service::query()
        ->where('is_active', true)
        ->orderBy('name')
        ->get();

    $servicesAreBookable = $activeServices->isNotEmpty();

    return view('welcome', [
        'applicationSettings' => ApplicationSetting::current(),
        'services' => $servicesAreBookable
            ? $activeServices
            : collect(config('casa.service_packages', []))->map(static fn (array $package): object => (object) $package),
        'servicesAreBookable' => $servicesAreBookable,
        'addons' => Addon::tableAvailable()
            ? Addon::query()->where('is_active', true)->orderBy('name')->get()
            : collect(),
    ]);
});

Route::get('/privacy-policy', function () {
    return view('privacy-policy', [
        'applicationSettings' => ApplicationSetting::current(),
    ]);
})->name('privacy-policy');

Route::get('/dashboard', function (Request $request) {
    return redirect()->route($request->user()->homeRouteName());
})->middleware(['auth', 'active', 'verified'])->name('dashboard');

Route::middleware(['auth', 'active', 'verified', MeasureApplicationRequest::class])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->middleware('throttle:user-sensitive')->name('profile.destroy');
    Route::get('/profile/delete/google', [GoogleDeletionController::class, 'redirect'])->middleware('throttle:user-sensitive')->name('profile.deletion.google');
    Route::get('/profile/delete/google/callback', [GoogleDeletionController::class, 'callback'])->middleware('throttle:user-sensitive')->name('profile.deletion.google.callback');
    Route::get('/profile/password/google', [GooglePasswordSetupController::class, 'redirect'])->middleware('throttle:user-sensitive')->name('profile.password.google');
    Route::get('/profile/password/google/callback', [GooglePasswordSetupController::class, 'callback'])->middleware('throttle:user-sensitive')->name('profile.password.google.callback');
});

Route::middleware(['auth', 'active', 'verified', 'role:super_admin,admin', MeasureApplicationRequest::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');
        Route::get('/attendance', [WebAttendanceController::class, 'index'])->name('attendance.index');
        Route::get('/attendance/station', [WebAttendanceController::class, 'station'])->name('attendance.station');
        Route::patch('/attendance/{attendance}/correct', [WebAttendanceController::class, 'correct'])->name('attendance.correct');
        Route::get('/appointments/calendar', AdminAppointmentCalendarController::class)->name('appointments.calendar');
        Route::get('/staff-schedule-roster', [AdminWeeklyRosterController::class, 'show'])->name('staff-roster.show');
        Route::post('/staff-schedule-roster/copy', [AdminWeeklyRosterController::class, 'copy'])->name('staff-roster.copy');
        Route::post('/staff-schedule-roster/{scheduleWeek}/shifts', [AdminWeeklyRosterController::class, 'storeShift'])->name('staff-roster.shifts.store');
        Route::delete('/staff-schedule-roster/{scheduleWeek}/shifts/{shift}', [AdminWeeklyRosterController::class, 'destroyShift'])->name('staff-roster.shifts.destroy');
        Route::post('/staff-schedule-roster/{scheduleWeek}/publish', [AdminWeeklyRosterController::class, 'publish'])->name('staff-roster.publish');
        Route::post('/appointments/calendar', [AdminAppointmentController::class, 'storeFromCalendar'])->name('appointments.calendar.store');
        Route::get('/appointments/available-therapists', [AdminAppointmentController::class, 'availableTherapists'])->name('appointments.available-therapists');
        Route::post('/appointments/{appointment}/complete', [AdminAppointmentController::class, 'complete'])->name('appointments.complete');
        Route::patch('/appointments/{appointment}/outcome', [AdminAppointmentController::class, 'outcome'])->name('appointments.outcome');
        Route::resource('appointments', AdminAppointmentController::class)->except('destroy');
        Route::resource('customers', AdminCustomerController::class)->only(['index', 'show', 'update']);
        Route::get('/staff/{staff}/weekly-schedules/create', [AdminStaffWeeklyScheduleController::class, 'create'])->name('staff.weekly-schedules.create');
        Route::post('/staff/{staff}/weekly-schedules', [AdminStaffWeeklyScheduleController::class, 'store'])->name('staff.weekly-schedules.store');
        Route::get('/staff/{staff}/weekly-schedules/{weeklySchedule}/edit', [AdminStaffWeeklyScheduleController::class, 'edit'])->name('staff.weekly-schedules.edit');
        Route::patch('/staff/{staff}/weekly-schedules/{weeklySchedule}', [AdminStaffWeeklyScheduleController::class, 'update'])->name('staff.weekly-schedules.update');
        Route::delete('/staff/{staff}/weekly-schedules/{weeklySchedule}', [AdminStaffWeeklyScheduleController::class, 'destroy'])->name('staff.weekly-schedules.destroy');
        Route::get('/staff/{staff}/schedule-exceptions/create', [AdminStaffScheduleExceptionController::class, 'create'])->name('staff.schedule-exceptions.create');
        Route::post('/staff/{staff}/schedule-exceptions', [AdminStaffScheduleExceptionController::class, 'store'])->name('staff.schedule-exceptions.store');
        Route::get('/staff/{staff}/schedule-exceptions/{scheduleException}/edit', [AdminStaffScheduleExceptionController::class, 'edit'])->name('staff.schedule-exceptions.edit');
        Route::patch('/staff/{staff}/schedule-exceptions/{scheduleException}', [AdminStaffScheduleExceptionController::class, 'update'])->name('staff.schedule-exceptions.update');
        Route::delete('/staff/{staff}/schedule-exceptions/{scheduleException}', [AdminStaffScheduleExceptionController::class, 'destroy'])->name('staff.schedule-exceptions.destroy');
        Route::resource('staff', AdminStaffController::class)->except('destroy');
        Route::patch('/services/{service}/toggle', [AdminServiceController::class, 'toggle'])->name('services.toggle');
        Route::resource('services', AdminServiceController::class)->except('destroy');
        Route::patch('/addons/{addon}/toggle', [AdminAddonController::class, 'toggle'])->name('addons.toggle');
        Route::resource('addons', AdminAddonController::class)->only(['index', 'create', 'store', 'edit', 'update']);
        Route::resource('transactions', AdminTransactionController::class)->except('destroy');
        Route::get('/commissions', [AdminCommissionController::class, 'index'])->name('commissions.index');
        Route::get('/commissions/{commission}', [AdminCommissionController::class, 'show'])->name('commissions.show');
        Route::patch('/commissions/{commission}/pay', [AdminCommissionController::class, 'pay'])->name('commissions.pay');
        Route::patch('/promotions/settings', [AdminPromotionController::class, 'updateSettings'])->name('promotions.settings.update');
        Route::patch('/promotions/{promotion}/dismiss', [AdminPromotionController::class, 'dismiss'])->name('promotions.dismiss');
        Route::resource('promotions', AdminPromotionController::class)->only(['index', 'show']);
        Route::resource('feedback', AdminFeedbackController::class)->only(['index', 'show']);
        Route::patch('/feedback/{feedback}/review', [AdminFeedbackController::class, 'review'])->name('feedback.review');
        Route::get('/reports/export', [AdminReportController::class, 'export'])->name('reports.export');
        Route::get('/reports', [AdminReportController::class, 'index'])->name('reports.index');
        Route::get('/settings', [AdminSettingController::class, 'index'])->name('settings.index');
        Route::patch('/settings', [AdminSettingController::class, 'update'])->name('settings.update');
    });

Route::middleware(['auth', 'active', 'verified', 'super_admin', MeasureApplicationRequest::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('users', AdminUserManagementController::class)->only(['index', 'store', 'update']);
        Route::get('/system-errors', [AdminSystemErrorReportController::class, 'index'])->name('system-errors.index');
        Route::get('/system-errors/{systemErrorReport}', [AdminSystemErrorReportController::class, 'show'])->name('system-errors.show');
        Route::patch('/system-errors/{systemErrorReport}/resolve', [AdminSystemErrorReportController::class, 'resolve'])->name('system-errors.resolve');
        Route::patch('/system-errors/{systemErrorReport}/reopen', [AdminSystemErrorReportController::class, 'reopen'])->name('system-errors.reopen');
    });

Route::middleware(['auth', 'active', 'verified', 'role:staff', MeasureApplicationRequest::class])
    ->prefix('staff')
    ->name('staff.')
    ->group(function () {
        Route::get('/dashboard', StaffDashboardController::class)->name('dashboard');
        Route::get('/attendance', [WebAttendanceController::class, 'staff'])->name('attendance.show');
        Route::post('/attendance/scan', [WebAttendanceController::class, 'submitScan'])->name('attendance.scan');
        Route::get('/appointments/calendar', StaffAppointmentCalendarController::class)->name('appointments.calendar');
        Route::resource('appointments', StaffAppointmentController::class)->only(['index', 'show']);
        Route::resource('customers', StaffCustomerController::class)->only(['index', 'show']);
        Route::resource('transactions', StaffTransactionController::class)->only(['index', 'show']);
        Route::resource('commissions', StaffCommissionController::class)->only(['index', 'show']);
        Route::resource('feedback', StaffFeedbackController::class)->only(['index', 'show']);
    });

Route::middleware(['auth', 'active', 'verified', 'role:receptionist', MeasureApplicationRequest::class])
    ->prefix('reception')
    ->name('reception.')
    ->group(function () {
        Route::get('/dashboard', ReceptionDashboardController::class)->name('dashboard');
        Route::get('/attendance', [WebAttendanceController::class, 'station'])->name('attendance.station');
        Route::get('/appointments/calendar', ReceptionAppointmentCalendarController::class)->name('appointments.calendar');
        Route::get('/appointments/available-therapists', [ReceptionAppointmentController::class, 'availableTherapists'])->name('appointments.available-therapists');
        Route::post('/appointments/{appointment}/complete', [ReceptionAppointmentController::class, 'complete'])->name('appointments.complete');
        Route::patch('/appointments/{appointment}/outcome', [ReceptionAppointmentController::class, 'outcome'])->name('appointments.outcome');
        Route::resource('appointments', ReceptionAppointmentController::class)->except('destroy');
        Route::resource('customers', ReceptionCustomerController::class)->only(['index', 'show', 'update']);
        Route::resource('transactions', ReceptionTransactionController::class)->except('destroy');
    });

Route::middleware(['auth', 'active', 'verified', 'role:super_admin,admin,receptionist'])
    ->prefix('attendance-station')->name('attendance-station.')->group(function (): void {
        Route::get('/qr', [WebAttendanceController::class, 'qr'])->name('qr');
    });

Route::middleware(['auth', 'active', 'verified', 'role:customer', MeasureApplicationRequest::class])
    ->prefix('customer')
    ->name('customer.')
    ->group(function () {
        Route::get('/appointments/availability', [CustomerAppointmentController::class, 'availability'])->name('appointments.availability');
        Route::get('/appointments/calendar', CustomerAppointmentCalendarController::class)->name('appointments.calendar');
        Route::get('/appointments/history', [CustomerAppointmentController::class, 'history'])->name('appointments.history');
        Route::patch('/appointments/{appointment}/cancel', [CustomerAppointmentController::class, 'cancel'])->name('appointments.cancel');
        Route::resource('appointments', CustomerAppointmentController::class)->only(['index', 'create', 'store', 'show']);
        Route::resource('feedback', CustomerFeedbackController::class)->only(['index', 'create', 'store']);
        Route::redirect('/profile', '/profile')->name('profile.edit');
    });

require __DIR__.'/auth.php';
