<?php
// app/Http/Controllers/CtoController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\CtoApplication;
use App\Services\CtoService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log; // Corrected import
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CtoController extends Controller
{
    protected $ctoService;

    public function __construct(CtoService $ctoService)
    {
        $this->ctoService = $ctoService;
    }

    /**
     * Show CTO management page for an employee.
     * Recalculates balances on load to ensure data consistency.
     */
    public function index(Request $request)
    {
        $employee = null;
        
        if ($request->has('employee_id')) {
            $employee = Employee::find($request->employee_id);

            if ($employee) {
                // Always recalculate balances on page load to ensure data integrity
                $this->ctoService->recalculateBalancesForEmployee($employee);
                
                // Reload the ctoApplications relationship to get updated balances for display
                // The sorting for display will be handled in the Blade view using effective_date
                $employee->load('ctoApplications'); 
            }
        }

        // === CRITICAL CHANGE: Pass $ctoService to the view ===
        // The $this->ctoService instance is what you want to pass
        $ctoService = $this->ctoService; 
        return view('cto.index', compact('employee', 'ctoService'));
    }

    /**
     * Store CTO activity (credits earned).
     * Relies on CtoService for processing and recalculation.
     */
    public function storeActivity(Request $request)
    {
        Log::info('CTO Activity Request Data:', $request->all());

        $isSingleDayActivity = $request->has('is_single_day_activity') && $request->boolean('is_single_day_activity');

        $validationRules = [
            'employee_id' => 'required|exists:employees,id',
            'special_order' => 'required|string|max:255',
            'activity' => 'required|string',
            'credits_earned' => 'required|numeric|min:0',
            'date_of_activity_start' => 'required|date',
            'date_of_activity_end' => $isSingleDayActivity ? 'nullable|date' : 'required|date|after_or_equal:date_of_activity_start',
        ];

        try {
            $request->validate($validationRules);

            Log::info('Validation passed for CTO Activity.');

            $employee = Employee::findOrFail($request->employee_id);

            $endDateForStorage = $isSingleDayActivity
                ? $request->date_of_activity_start
                : $request->date_of_activity_end;

            $activityData = [
                'special_order' => $request->special_order,
                'date_of_activity_start' => $request->date_of_activity_start,
                'date_of_activity_end' => $endDateForStorage,
                'activity' => $request->activity,
                'credits_earned' => (float)$request->credits_earned,
            ];

            $ctoApplication = $this->ctoService->processCtoActivity($employee, $activityData);

            Log::info('CTO Application Created and Balances Recalculated:', $ctoApplication->toArray());

            return redirect()->route('cto.index', ['employee_id' => $employee->id])
                ->with('success', 'CTO activity added successfully!');

        } catch (ValidationException $e) {
            Log::error('CTO Activity Validation Error:', ['errors' => $e->errors(), 'request' => $request->all()]);
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput()
                ->with('error', 'Validation failed: Please check your input.');
        } catch (\Exception $e) {
            Log::error('CTO Activity General Error:', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->back()
                ->withInput()
                ->with('error', 'Error adding CTO activity: ' . $e->getMessage());
        }
    }

    /**
     * Store CTO usage (credits deducted).
     * This method triggers the FIFO deduction and expiration logic in CtoService.
     */
    public function storeUsage(Request $request)
    {
        Log::info('CTO Usage Request Data:', $request->all());

        $isSingleDayAbsence = $request->has('is_single_day_absence') && $request->boolean('is_single_day_absence');

        $validationRules = [
            'employee_id' => 'required|exists:employees,id',
            'date_of_absence_start' => 'required|date',
            'date_of_absence_end' => $isSingleDayAbsence ? 'nullable|date' : 'required|date|after_or_equal:date_of_absence_start',
        ];

        try {
            $request->validate($validationRules);

            Log::info('Validation passed for CTO Usage.');

            $employee = Employee::findOrFail($request->employee_id);

            $startDate = $request->date_of_absence_start;
            $endDateForStorage = $isSingleDayAbsence
                ? $startDate
                : $request->date_of_absence_end;

            $noOfDays = $this->ctoService->calculateWorkingDays($startDate, $endDateForStorage);

            // Check eligible balance BEFORE calling service to give immediate feedback
            $eligibleBalance = $this->ctoService->getEligibleCtoBalance($employee, Carbon::parse($startDate));
            if ($eligibleBalance < $noOfDays) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Insufficient eligible CTO balance. Available: ' . $eligibleBalance . ' days, Required: ' . $noOfDays . ' days');
            }

            $usageData = [
                'date_of_absence_start' => $startDate,
                'date_of_absence_end' => $endDateForStorage,
                'no_of_days' => $noOfDays,
            ];

            $ctoApplication = $this->ctoService->processCtoUsage($employee, $usageData);

            Log::info('CTO Usage Created and Balances Recalculated:', $ctoApplication->toArray());

            return redirect()->route('cto.index', ['employee_id' => $employee->id])
                ->with('success', 'CTO usage recorded successfully!');

        } catch (ValidationException $e) {
            Log::error('CTO Usage Validation Error:', ['errors' => $e->errors(), 'request' => $request->all()]);
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput()
                ->with('error', 'Validation failed: Please check your input.');
        } catch (\Exception $e) {
            // Catching insufficient eligible balance exception thrown from service
            Log::error('CTO Usage General Error:', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->back()
                ->withInput()
                ->with('error', 'Error recording CTO usage: ' . $e->getMessage());
        }
    }

    /**
     * Update CTO record.
     * Relies on CtoService for processing and recalculation.
     */
    public function update(Request $request, CtoApplication $ctoApplication)
    {
        $isSingleDayActivity = $request->has('is_single_day_activity') && $request->boolean('is_single_day_activity');
        $isSingleDayAbsence = $request->has('is_single_day_absence') && $request->boolean('is_single_day_absence');

        if ($ctoApplication->is_activity) {
            $validationRules = [
                'special_order' => 'required|string|max:255',
                'activity' => 'required|string',
                'credits_earned' => 'required|numeric|min:0',
                'date_of_activity_start' => 'required|date',
                'date_of_activity_end' => $isSingleDayActivity ? 'nullable|date' : 'required|date|after_or_equal:date_of_activity_start',
            ];
            $request->validate($validationRules);

            $endDateForStorage = $isSingleDayActivity
                ? $request->date_of_activity_start
                : $request->date_of_activity_end;

            $activityData = [
                'special_order' => $request->special_order,
                'date_of_activity_start' => $request->date_of_activity_start,
                'date_of_activity_end' => $endDateForStorage,
                'activity' => $request->activity,
                'credits_earned' => $request->credits_earned,
            ];

            $this->ctoService->processCtoActivity($ctoApplication->employee, $activityData, $ctoApplication);
        } else {
            $validationRules = [
                'date_of_absence_start' => 'required|date',
                'date_of_absence_end' => $isSingleDayAbsence ? 'nullable|date' : 'required|date|after_or_equal:date_of_absence_start',
            ];
            $request->validate($validationRules);

            $startDate = $request->date_of_absence_start;
            $endDateForStorage = $isSingleDayAbsence
                ? $startDate
                : $request->date_of_absence_end;

            $noOfDays = $this->ctoService->calculateWorkingDays($startDate, $endDateForStorage);

            $usageData = [
                'date_of_absence_start' => $startDate,
                'date_of_absence_end' => $endDateForStorage,
                'no_of_days' => $noOfDays,
            ];

            $this->ctoService->processCtoUsage($ctoApplication->employee, $usageData, $ctoApplication);
        }

        return redirect()->back()->with('success', 'CTO record updated successfully!');
    }

    /**
     * Delete CTO record.
     * Relies on CtoService for processing and recalculation.
     */
    public function destroy(CtoApplication $ctoApplication)
    {
        try {
            $this->ctoService->deleteCtoRecord($ctoApplication); 
            return redirect()->back()->with('success', 'CTO record deleted successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Get CTO data for editing (AJAX).
     */
    public function edit(CtoApplication $ctoApplication)
    {
        return response()->json($ctoApplication);
    }

    /**
     * Calculate days between dates (for AJAX).
     */
    public function calculateDays(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $days = $this->ctoService->calculateWorkingDays($request->start_date, $request->end_date);

        return response()->json(['days' => $days]);
    }

    /**
     * Add employee (redirect to correct route).
     * NOTE: This method is a duplicate of a method in LeaveController.
     * Consider refactoring employee management to a dedicated EmployeeController if appropriate.
     */
    public function addEmployee(Request $request)
    {
        $request->validate([
            'surname' => 'required|string',
            'given_name' => 'required|string',
            'middle_name' => 'required|string',
            'division' => 'required|string',
            'designation' => 'required|string',
            'original_appointment' => 'nullable|string',
            'salary' => 'required|numeric',
            'balance_forwarded_vl' => 'required|numeric',
            'balance_forwarded_sl' => 'required|numeric',
        ]);

        $employeeData = $request->all();
        $employeeData['vl'] = $employeeData['balance_forwarded_vl'] ?? 0;
        $employeeData['sl'] = $employeeData['balance_forwarded_sl'] ?? 0;
        $employeeData['spl'] = $employeeData['spl'] ?? 3;
        $employeeData['fl'] = $employeeData['fl'] ?? 0;
        $employeeData['solo_parent'] = $employeeData['solo_parent'] ?? 7;
        $employeeData['ml'] = $employeeData['ml'] ?? 105;
        $employeeData['pl'] = $employeeData['pl'] ?? 7;
        $employeeData['ra9710'] = 0; // Fixed default value
        $employeeData['rl'] = 0;    // Fixed default value
        $employeeData['sel'] = 0;   // Fixed default value
        $employeeData['study_leave'] = 0; // Fixed default value
        $employeeData['adopt'] = 0;     // Fixed default value


        $employee = Employee::create($employeeData);

        return redirect()->route('cto.index', ['employee_id' => $employee->id])
            ->with('success', '✅ Employee Added!');
    }
}