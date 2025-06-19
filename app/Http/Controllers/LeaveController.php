<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee; 
use App\Models\LeaveApplication; 

class LeaveController extends Controller
{
    //{
    public function index(Request $request)
    {
        $employee = null;
        $message = '';

        if ($request->has('employee_id')) {
            $employee = Employee::find($request->employee_id);
        }

        return view('leave.index', compact('employee', 'message'));
    }

    public function addEmployee(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'division' => 'required|string',
            'designation' => 'required|string',
            'salary' => 'required|numeric',
        ]);

        $employee = Employee::create($request->all());

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

        LeaveApplication::create($request->all());

        return redirect()->route('leave.index', ['employee_id' => $request->employee_id])
            ->with('success', 'Application submitted successfully!');
    }

    public function addCreditsEarned(Request $request)
    {
        $employee = Employee::find($request->employee_id);
        $lastApplication = $employee->leaveApplications()->latest()->first();
        
        $currentVL = $lastApplication ? $lastApplication->current_vl : $employee->balance_forwarded_vl;
        $currentSL = $lastApplication ? $lastApplication->current_sl : $employee->balance_forwarded_sl;

        LeaveApplication::create([
            'employee_id' => $request->employee_id,
            'current_vl' => $currentVL + 1.25,
            'current_sl' => $currentSL + 1.25,
            'is_credit_earned' => true,
            'earned_date' => $request->earned_date,
        ]);

        return redirect()->route('leave.index', ['employee_id' => $request->employee_id]);
    }

    public function addLeaveRow(Request $request)
    {
        $employee = Employee::find($request->employee_id);
        $lastApplication = $employee->leaveApplications()->latest()->first();
        
        $currentVL = $lastApplication ? $lastApplication->current_vl : $employee->balance_forwarded_vl;
        $currentSL = $lastApplication ? $lastApplication->current_sl : $employee->balance_forwarded_sl;

        $newVL = $currentVL;
        $newSL = $currentSL;

        if ($request->leave_type === 'VL') {
            $newVL -= $request->working_days;
        } elseif ($request->leave_type === 'SL') {
            $newSL -= $request->working_days;
        }

        LeaveApplication::create([
            'employee_id' => $request->employee_id,
            'leave_type' => $request->leave_type,
            'working_days' => $request->working_days,
            'date_filed' => $request->leave_date_filed,
            'date_incurred' => $request->leave_date_incurred,
            'current_vl' => $newVL,
            'current_sl' => $newSL,
        ]);

        return redirect()->route('leave.index', ['employee_id' => $request->employee_id]);
    }

}
