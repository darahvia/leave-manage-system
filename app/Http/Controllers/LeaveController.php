<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee; 
use App\Models\LeaveApplication; 
use App\Services\LeaveService;


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
            'name' => 'required|string',
            'division' => 'required|string',
            'designation' => 'required|string',
            'salary' => 'required|numeric',
        ]);

        $employeeData=request->al();
        $employeeData['vl'] = $employeeData['vl'] ?? 0;
        $employeeData['sl'] = $employeeData['sl'] ?? 0;
        $employeeData['spl'] = $employeeData['spl'] ?? 3; // Usually 3 days per year
        $employeeData['fl'] = $employeeData['fl'] ?? 5; // Usually 5 days per year
        $employeeData['solo_parent'] = $employeeData['solo_parent'] ?? 7;
        $employeeData['ml'] = $employeeData['ml'] ?? 105; // 105 days for maternity
        $employeeData['pl'] = $employeeData['pl'] ?? 7; // 7 days for paternity
        $employeeData['ra9710'] = $employeeData['ra9710'] ?? 0;
        $employeeData['rl'] = $employeeData['rl'] ?? 0;
        $employeeData['sel'] = $employeeData['sel'] ?? 0;
        $employeeData['study_leave'] = $employeeData['study_leave'] ?? 0;

        $employee = Employee::create($employeeData);

        return redirect()->route('employee.find', ['name' => $employee->name])
            ->with('success', '✅ Employee Added!');

    }

    public function findEmployee(Request $request)
    {
        $employee = Employee::where('name', $request->name)->first();

        if ($employee) {
            return redirect()->route('leave.index', ['employee_id' => $employee->id]);
        }

        return redirect()->route('leave.index')
            ->with('error', '❌ Employee not found.');
    }

    public function submitLeave(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type' => 'required|string',
            'working_days' => 'required|integer|min:1',
            'date_filed' => 'required|date',
            'date_incurred' => 'required|date',
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


    public function addCreditsEarned(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'earned_date' => 'required|date',
        ]);

        try {
            $employee = Employee::find($request->employee_id);
            
            $this->leaveService->addCreditsEarned(
                $employee,
                $request->earned_date,
                1.25, // VL credits
                1.25  // SL credits
            );

            return redirect()->route('leave.index', ['employee_id' => $request->employee_id])
                ->with('success', '✅ Leave credits added successfully!');

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
}
