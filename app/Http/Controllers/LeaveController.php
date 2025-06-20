<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee; 
use App\Models\LeaveApplication; 
use App\Services\LeaveService;
use Carbon\Carbon;


class LeaveController extends Controller
{
    protected $leaveService;
    
    public function __construct(LeaveService $leaveService)
    {
        $this->leaveService = $leaveService;
    }

    public function index(Request $request)
    {
        $employee = null;
        $leaveTypes = LeaveService::getLeaveTypes();
        $message = '';

        if ($request->has('employee_id')) {
            $employee = Employee::find($request->employee_id);
        }

        return view('leave.index', compact('employee', 'leaveTypes'));
    }

    public function addEmployee(Request $request)
    {
        $request->validate([
            'surname' => 'required|string',
            'given_name' => 'required|string',
            'middle_name' => 'required|string',
            'division' => 'required|string',
            'designation' => 'required|string',
            'original_appointment' => 'required|string',
            'balance_forwarded_vl' => 'nullable|numeric',
            'balance_forwarded_sl' => 'nullable|numeric',
        ]);

        $employeeData = $request->all();
        $employeeData['vl'] = $employeeData['vl'] ?? 0;
        $employeeData['sl'] = $employeeData['sl'] ?? 0;
        $employeeData['spl'] = $employeeData['spl'] ?? 3;
        $employeeData['fl'] = $employeeData['fl'] ?? 0;
        $employeeData['solo_parent'] = $employeeData['solo_parent'] ?? 7;
        $employeeData['ml'] = $employeeData['ml'] ?? 105;
        $employeeData['pl'] = $employeeData['pl'] ?? 7;
        $employeeData['ra9710'] = $employeeData['ra9710'] ?? 0;
        $employeeData['rl'] = $employeeData['rl'] ?? 0;
        $employeeData['sel'] = $employeeData['sel'] ?? 0;
        $employeeData['study_leave'] = $employeeData['study_leave'] ?? 0;

        $employee = Employee::create($employeeData);

        $fullName = "{$employee->surname}, {$employee->given_name} {$employee->middle_name}";

        return redirect()->route('employee.find', ['name' => $fullName])
            ->with('success', '✅ Employee Added!');
        }

        public function findEmployee(Request $request)
        {
            $employee = Employee::whereRaw("CONCAT(surname, ', ', given_name, ' ', middle_name) = ?", [$request->name])
                ->first();

            if ($employee) {
                return redirect()->route('leave.index', ['employee_id' => $employee->id]);
            }

            return redirect()->route('leave.index')
                ->with('error', '❌ Employee not found.');
        }

        public function updateLeave(Request $request)
        {
            try {
                $request->validate([
                    'edit_id' => 'required|integer',
                    'employee_id' => 'required|integer',
                    'leave_type' => 'required|string',
                    'date_filed' => 'required|date',
                    'inclusive_date_start' => 'required|date',
                    'inclusive_date_end' => 'required|date',
                    'working_days' => 'required|numeric',
                ]);

                // Find the leave application to update
                $leaveApplication = LeaveApplication::findOrFail($request->edit_id);
                
                // Verify that this leave application belongs to the specified employee
                if ($leaveApplication->employee_id != $request->employee_id) {
                    return back()->with('error', 'Unauthorized access to leave application.');
                }

                // Update the leave application
                $leaveApplication->update([
                    'leave_type' => $request->leave_type,
                    'date_filed' => $request->date_filed,
                    'inclusive_date_start' => $request->inclusive_date_start,
                    'inclusive_date_end' => $request->inclusive_date_end,
                    'working_days' => $request->working_days,
                ]);

                // You might want to recalculate balances here if needed
                // $this->leaveService->recalculateBalances($request->employee_id);

                return back()->with('success', 'Leave application updated successfully.');
                
            } catch (ValidationException $e) {
                return back()->withErrors($e->errors())->withInput();
            } catch (\Exception $e) {
                return back()->with('error', 'An error occurred while updating the leave application: ' . $e->getMessage());
            }
        }
        public function deleteLeave(Request $request)
        {
            try {
                $request->validate([
                    'id' => 'required|integer',
                    'type' => 'required|in:leave,credit'
                ]);

                $leaveApplication = LeaveApplication::findOrFail($request->id);
                $leaveApplication->delete();

                $recordType = $request->type === 'credit' ? 'credit entry' : 'leave application';
                
                // Return JSON for AJAX requests
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => true,
                        'message' => ucfirst($recordType) . ' deleted successfully.'
                    ]);
                }

                return back()->with('success', ucfirst($recordType) . ' deleted successfully.');
                
            } catch (\Exception $e) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'An error occurred while deleting the record: ' . $e->getMessage()
                    ], 500);
                }
                
                return back()->with('error', 'An error occurred while deleting the record: ' . $e->getMessage());
            }
        }

        public function submitLeave(Request $request)
        {
            $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'leave_type' => 'required|string',
                'working_days' => 'required|integer|min:1',
                'date_filed' => 'required|date',
                'inclusive_date_start' => 'required|date',
                'inclusive_date_end' => 'required|date|after_or_equal:inclusive_date_start',
            ]);

            try {
                $employee = Employee::find($request->employee_id);
                
                $leaveApplication = $this->leaveService->processLeaveApplication(
                    $employee,
                    $request->all()
                );

                $leaveTypeName = LeaveService::getLeaveTypes()[$request->leave_type] ?? $request->leave_type;
                
                return redirect()->route('leave.index', ['employee_id' => $request->employee_id])
                    ->with('success', "✅ {$leaveTypeName} application submitted successfully!");

            } catch (\Exception $e) {
                return redirect()->route('leave.index', ['employee_id' => $request->employee_id])
                    ->with('error', '❌ ' . $e->getMessage());
            }
        }

        public function addOtherCreditsEarned(Request $request)
        {
            $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'leave_type' => 'required|string',
                'credits' => 'required|numeric|min:0',
            ]);

            try {
                $employee = Employee::findOrFail($request->employee_id);
                $leaveType = strtolower($request->leave_type);
                $credits = $request->credits;

                if (!in_array($leaveType, [
                    'spl', 'fl', 'solo_parent', 'ml', 'pl', 'ra9710', 'rl', 'sel', 'study_leave'
                ])) {
                    throw new \Exception('Invalid leave type.');
                }

                $employee->{$leaveType} += $credits;
                $employee->save();

                return redirect()->route('leave.index', ['employee_id' => $employee->id])
                    ->with('success', '✅ Other leave credits added successfully!');
                    
            } catch (\Exception $e) {
                return redirect()->route('leave.index', ['employee_id' => $request->employee_id])
                    ->with('error', '❌ ' . $e->getMessage());
            }
        }


    public function getEmployeeLeaveBalances($employeeId)
    {
        $employee = Employee::find($employeeId);
        
        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        $balances = [];
        $leaveTypes = ['vl', 'sl', 'spl', 'fl', 'solo_parent', 'ml', 'pl', 'ra9710', 'rl', 'sel', 'study_leave'];
        
        foreach ($leaveTypes as $type) {
            $balances[$type] = $employee->getCurrentLeaveBalance($type);
        }

        return response()->json($balances);
    }

    public function employeeAutocomplete(Request $request)
    {
        if (ob_get_level()) {
            ob_clean();
        }

        $search = $request->get('query');

        if (empty($search) || strlen($search) < 2) {
            return response()->json([]);
        }

        try {
            $results = Employee::where(function ($query) use ($search) {
                    $query->where('surname', 'LIKE', "%{$search}%")
                        ->orWhere('given_name', 'LIKE', "%{$search}%")
                        ->orWhere('middle_name', 'LIKE', "%{$search}%");
                })
                ->limit(10)
                ->get(['surname', 'given_name', 'middle_name', 'id'])
                ->map(function ($employee) {
                    return [
                        'id' => $employee->id,
                        'label' => trim("{$employee->surname}, {$employee->given_name} {$employee->middle_name}"),
                    ];
                })
                ->values()
                ->toArray();

            return response()->json($results, 200, [
                'Content-Type' => 'application/json'
            ]);
        } catch (\Exception $e) {
            return response()->json([], 500);
        }
    }
}
