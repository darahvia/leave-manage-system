<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LeaveController;

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
