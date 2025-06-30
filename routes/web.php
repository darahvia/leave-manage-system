<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\TeachingLeaveController;


// Entry point selection screen
Route::get('/', function () {
    return view('leave.select');
})->name('leave.select');

// Non-teaching (employee) routes
Route::prefix('leave/employee')->group(function () {
    Route::get('/', [LeaveController::class, 'index'])->name('leave.employee.index');
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
});

// Teaching routes — new controller
Route::prefix('leave/teaching')->group(function () {
     Route::get('/', [TeachingLeaveController::class, 'index'])->name('leave.teaching.index');
    
    // Add new teaching employee
    Route::post('/add-employee', [TeachingLeaveController::class, 'addEmployee'])->name('teaching.add');
    
    // Find teaching employee
    Route::any('/find-employee', [TeachingLeaveController::class, 'findEmployee'])->name('teaching.find');
    
    // Submit new leave application
    Route::post('/submit-leave', [TeachingLeaveController::class, 'submitLeave'])->name('teaching.leave.submit');
    
    // Update existing leave application
    Route::put('/update-leave', [TeachingLeaveController::class, 'updateLeave'])->name('teaching.leave.update');
    
    // Delete leave application or credit record
    Route::delete('/delete-leave', [TeachingLeaveController::class, 'deleteLeave'])->name('teaching.leave.delete');
    
    // Add credits earned
    Route::post('/add-credits', [TeachingLeaveController::class, 'addCreditsEarned'])->name('teaching.credits.add');
    
    // Teaching employee search/autocomplete
    Route::get('/search', [TeachingLeaveController::class, 'searchEmployee'])->name('teaching.search');;
    // add more routes here like /submit, /update etc., pointing to TeachingLeaveController
});


// Route::get('/', [LeaveController::class, 'index'])->name('leave.index');
// Route::post('/add-employee', [LeaveController::class, 'addEmployee'])->name('employee.add');
// Route::any('/find-employee', [LeaveController::class, 'findEmployee'])->name('employee.find');
// Route::post('/submit-leave', [LeaveController::class, 'submitLeave'])->name('leave.submit');
// Route::put('/update-leave', [LeaveController::class, 'updateLeave'])->name('leave.update');

// Route::delete('/delete-leave', [LeaveController::class, 'deleteLeave'])->name('leave.delete');
// Route::post('/add-credits', [LeaveController::class, 'addCreditsEarned'])->name('leave.credits');
// Route::post('/add-leave-row', [LeaveController::class, 'addLeaveRow'])->name('leave.row');
// Route::post('/add-otherCredits', [LeaveController::class, 'addOtherCreditsEarned'])->name('leave.otherCredits');


// // Make sure this route is GET method only and comes before any catch-all routes
// Route::get('/employee-autocomplete', [LeaveController::class, 'employeeAutocomplete'])->name('employee.autocomplete');
