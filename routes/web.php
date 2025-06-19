// routes/web.php
<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LeaveController;

Route::get('/', [LeaveController::class, 'index'])->name('leave.index');
Route::post('/add-employee', [LeaveController::class, 'addEmployee'])->name('employee.add');
Route::any('/find-employee', [LeaveController::class, 'findEmployee'])->name('employee.find');
Route::post('/submit-leave', [LeaveController::class, 'submitLeave'])->name('leave.submit');
Route::post('/add-credits', [LeaveController::class, 'addCreditsEarned'])->name('leave.credits');
Route::post('/add-leave-row', [LeaveController::class, 'addLeaveRow'])->name('leave.row');

// /add-leave-row - url path the rounte is responing to
// LeaveController::class, 'addLeaveRow' - controller and method to handle the request
// leave.row - name of the route, used for generating URLs in views