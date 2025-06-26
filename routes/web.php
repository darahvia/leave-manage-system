<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\CtoController;


Route::get('/', [LeaveController::class, 'index'])->name('leave.index');
Route::post('/add-employee', [LeaveController::class, 'addEmployee'])->name('employee.add');
Route::any('/find-employee', [LeaveController::class, 'findEmployee'])->name('employee.find');
Route::post('/submit-leave', [LeaveController::class, 'submitLeave'])->name('leave.submit');
Route::put('/update-leave', [LeaveController::class, 'updateLeave'])->name('leave.update');
Route::delete('/delete-leave', [LeaveController::class, 'deleteLeave'])->name('leave.delete');
Route::post('/add-credits', [LeaveController::class, 'addCreditsEarned'])->name('leave.credits');
Route::post('/add-leave-row', [LeaveController::class, 'addLeaveRow'])->name('leave.row');
Route::post('/add-otherCredits', [LeaveController::class, 'addOtherCreditsEarned'])->name('leave.otherCredits');


// Make sure this route is GET method only and comes before any catch-all routes
Route::get('/employee-autocomplete', [LeaveController::class, 'employeeAutocomplete'])->name('employee.autocomplete');


// CTO routes
Route::prefix('cto')->group(function () {
    Route::get('/', [CtoController::class, 'index'])->name('cto.index');
    Route::post('/add-employee', [CtoController::class, 'addEmployee'])->name('cto.employee.add');
    Route::post('/store-activity', [CtoController::class, 'storeActivity'])->name('cto.store-activity');
    Route::post('/store-usage', [CtoController::class, 'storeUsage'])->name('cto.store-usage');
    Route::get('/{ctoApplication}/edit', [CtoController::class, 'edit'])->name('cto.edit');
    Route::put('/{ctoApplication}', [CtoController::class, 'update'])->name('cto.update');
    Route::delete('/{ctoApplication}', [CtoController::class, 'destroy'])->name('cto.destroy');
    Route::post('/calculate-days', [CtoController::class, 'calculateDays'])->name('cto.calculate-days');
});