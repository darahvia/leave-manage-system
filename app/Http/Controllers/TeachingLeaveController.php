<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Teaching; 
use App\Models\TeachingLeaveApplications; 
use App\Models\TeachingEarnedCredits;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class TeachingLeaveController extends Controller
{
    public function index(Request $request)
    {
        $employee = null;
        $teachingLeaveApplications = collect();
        $teachingEarnedCredits = collect();

        if ($request->has('employee_id')) {
            $employee = Teaching::find($request->employee_id);
            if ($employee) {
                // Debug: Log what we're about to do
                \Log::info('About to query teaching_leave_applications for employee_id: ' . $employee->id);
                
                try {
                    // Get leave applications from teaching_leave_applications table
                    $query = TeachingLeaveApplications::where('employee_id', $employee->id);
                    
                    // Debug: Log the base query
                    \Log::info('Base query SQL: ' . $query->toSql());
                    
                    // Add ordering - let's be very explicit
                    $query = $query->orderBy('leave_incurred_date', 'desc')
                                   ->orderBy('created_at', 'desc');
                    
                    // Debug: Log the final query
                    \Log::info('Final query SQL: ' . $query->toSql());
                    \Log::info('Query bindings: ' . json_encode($query->getBindings()));
                    
                    $teachingLeaveApplications = $query->get();
                    
                } catch (\Exception $e) {
                    \Log::error('Error in leave applications query: ' . $e->getMessage());
                    // Try without ordering to see if that works
                    $teachingLeaveApplications = TeachingLeaveApplications::where('employee_id', $employee->id)->get();
                }

                try {
                    // Get earned credits from teaching_earned_credits table
                    $teachingEarnedCredits = TeachingEarnedCredits::where('employee_id', $employee->id)
                        ->orderBy('earned_date', 'desc')
                        ->orderBy('created_at', 'desc')
                        ->get();
                } catch (\Exception $e) {
                    \Log::error('Error in earned credits query: ' . $e->getMessage());
                    $teachingEarnedCredits = collect();
                }
            }
        }

        return view('leave.teaching.index', compact('employee', 'teachingLeaveApplications', 'teachingEarnedCredits'));
    }

public function addEmployee(Request $request)
{
    try {
        $request->validate([
            'surname' => 'required|string|max:255',
            'given_name' => 'required|string|max:255',
            'middle_name' => 'required|string|max:255',
            'sex' => 'required|in:Male,Female',
            'civil_status' => 'nullable|string|max:50',
            'date_of_birth' => 'nullable|date|before:today',
            'place_of_birth' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'name_of_school' => 'required|string|max:255',
            'permanency' => 'required|in:Permanent,Temporary,Contractual',
            'employee_number' => 'nullable|string|max:50|unique:teaching,employee_number',
            'salary' => 'nullable|numeric|min:0',
            'leave_credits' => 'nullable|numeric|min:0',
        ]);

        $teachingData = $request->all();
        
        if (!isset($teachingData['leave_credits'])) {
            $teachingData['leave_credits'] = 0;
        }

        $teaching = Teaching::create($teachingData);

        $fullName = "{$teaching->surname}, {$teaching->given_name} {$teaching->middle_name}";

        return redirect()->route('teaching.find', ['name' => $fullName])
            ->with('success', '✅ Teaching Employee Added Successfully!');

    } catch (ValidationException $e) {
        return back()->withErrors($e->errors())->withInput();
    } catch (\Exception $e) {
        return back()->with('error', '❌ An error occurred while adding employee: ' . $e->getMessage())
                     ->withInput();
    }
}


    public function findEmployee(Request $request)
    {
        $request->validate([
            'name' => 'required|string'
        ]);

        $employee = Teaching::whereRaw("CONCAT(surname, ', ', given_name, ' ', middle_name) = ?", [$request->name])
            ->first();

        if ($employee) {
            return redirect()->route('leave.teaching.index', ['employee_id' => $employee->id]);
        }

        return redirect()->route('leave.teaching.index')
            ->with('error', '❌ Teaching employee not found.');
    }

    public function submitLeave(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:teaching,id',
            'leave_incurred_date' => 'required|date',
            'leave_incurred_days' => 'required|integer|min:1|max:365',
        ]);

        try {
            $teaching = Teaching::findOrFail($request->employee_id);

            // Check if employee has sufficient leave credits
            if ($teaching->leave_credits < $request->leave_incurred_days) {
                return redirect()->route('leave.teaching.index', ['employee_id' => $teaching->id])
                    ->with('error', '❌ Insufficient leave credits. Available: ' . $teaching->leave_credits . ' days');
            }

            // Create leave application
            TeachingLeaveApplications::create([
                'employee_id' => $teaching->id,
                'leave_incurred_date' => $request->leave_incurred_date,
                'leave_incurred_days' => $request->leave_incurred_days,
            ]);

            // Deduct leave credits from employee
            $teaching->leave_credits -= $request->leave_incurred_days;
            $teaching->save();

            return redirect()->route('leave.teaching.index', ['employee_id' => $teaching->id])
                ->with('success', '✅ Teaching leave application submitted successfully!');

        } catch (\Exception $e) {
            return redirect()->route('leave.teaching.index', ['employee_id' => $request->employee_id])
                ->with('error', '❌ An error occurred: ' . $e->getMessage());
        }
    }

    public function updateLeave(Request $request)
    {
        try {
            $request->validate([
                'edit_id' => 'required|integer|exists:teaching_leave_applications,id',
                'employee_id' => 'required|integer|exists:teaching,id',
                'leave_incurred_date' => 'required|date',
                'leave_incurred_days' => 'required|integer|min:1|max:365',
            ]);

            $employee = Teaching::findOrFail($request->employee_id);
            $leaveApplication = TeachingLeaveApplications::findOrFail($request->edit_id);
            
            // Verify that this leave application belongs to the specified employee
            if ($leaveApplication->employee_id != $request->employee_id) {
                return back()->with('error', '❌ Unauthorized access to leave application.');
            }

            // Calculate the difference in days
            $oldDays = $leaveApplication->leave_incurred_days;
            $newDays = $request->leave_incurred_days;
            $daysDifference = $newDays - $oldDays;

            // Check if employee has sufficient credits for additional days
            if ($daysDifference > 0 && $employee->leave_credits < $daysDifference) {
                return back()->with('error', '❌ Insufficient leave credits for update. Available: ' . $employee->leave_credits . ' days');
            }

            // Update the leave application
            $leaveApplication->update([
                'leave_incurred_date' => $request->leave_incurred_date,
                'leave_incurred_days' => $request->leave_incurred_days,
            ]);

            // Adjust employee's leave credits
            $employee->leave_credits -= $daysDifference;
            $employee->save();

            return back()->with('success', '✅ Leave application updated successfully.');
            
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            return back()->with('error', '❌ An error occurred while updating: ' . $e->getMessage());
        }
    }

    public function deleteLeave(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer|exists:teaching_leave_applications,id',
                'employee_id' => 'required|integer|exists:teaching,id'
            ]);

            $leaveApplication = TeachingLeaveApplications::findOrFail($request->id);
            $employee = Teaching::findOrFail($request->employee_id);

            // Verify that this leave application belongs to the specified employee
            if ($leaveApplication->employee_id != $request->employee_id) {
                return back()->with('error', '❌ Unauthorized access to leave application.');
            }

            // Restore leave credits to employee
            $employee->leave_credits += $leaveApplication->leave_incurred_days;
            $employee->save();

            // Delete the leave application
            $leaveApplication->delete();
            
            // Return JSON for AJAX requests
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Leave application deleted successfully.'
                ]);
            }

            return back()->with('success', '✅ Leave application deleted successfully.');
            
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'An error occurred while deleting: ' . $e->getMessage()
                ], 500);
            }
            
            return back()->with('error', '❌ An error occurred while deleting: ' . $e->getMessage());
        }
    }

    public function addCreditsEarned(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:teaching,id',
            'credits_to_add' => 'required|numeric|min:0.01|max:50',
            'earned_date' => 'required|string',
            'special_order' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:255'
        ]);

        try {
            $employee = Teaching::findOrFail($request->employee_id);
            
            // Add credits to employee
            $employee->leave_credits += $request->credits_to_add;
            $employee->save();

            // Create a record for the credit addition in teaching_earned_credits table
            TeachingEarnedCredits::create([
                'employee_id' => $employee->id,
                'earned_date' => $request->earned_date,
                'days' => $request->credits_to_add,
                'reference' => $request->reference ?? 'CREDIT_EARNED',
                'special_order' => $request->special_order ?? 'Leave credits earned',
            ]);

            return redirect()->route('leave.teaching.index', ['employee_id' => $request->employee_id])
                ->with('success', '✅ Leave credits added successfully! Added: ' . $request->credits_to_add . ' days');

        } catch (\Exception $e) {
            return redirect()->route('leave.teaching.index', ['employee_id' => $request->employee_id])
                ->with('error', '❌ An error occurred: ' . $e->getMessage());
        }
    }

    public function deleteCredit(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer|exists:teaching_earned_credits,id',
                'employee_id' => 'required|integer|exists:teaching,id'
            ]);

            $earnedCredit = TeachingEarnedCredits::findOrFail($request->id);
            $employee = Teaching::findOrFail($request->employee_id);

            // Verify that this earned credit belongs to the specified employee
            if ($earnedCredit->employee_id != $request->employee_id) {
                return back()->with('error', '❌ Unauthorized access to earned credit.');
            }

            // Deduct credits from employee (reverse the credit addition)
            $employee->leave_credits -= $earnedCredit->days;
            $employee->save();

            // Delete the earned credit record
            $earnedCredit->delete();
            
            // Return JSON for AJAX requests
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Earned credit deleted successfully.'
                ]);
            }

            return back()->with('success', '✅ Earned credit deleted successfully.');
            
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'An error occurred while deleting: ' . $e->getMessage()
                ], 500);
            }
            
            return back()->with('error', '❌ An error occurred while deleting: ' . $e->getMessage());
        }
    }

    public function searchEmployee(Request $request)
    {
        $request->validate([
            'search' => 'required|string|min:2'
        ]);

        $searchTerm = $request->search;
        
        $employees = Teaching::where(function($query) use ($searchTerm) {
            $query->whereRaw("CONCAT(surname, ', ', given_name, ' ', middle_name) LIKE ?", ["%{$searchTerm}%"])
                  ->orWhere('employee_number', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('surname', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('given_name', 'LIKE', "%{$searchTerm}%");
        })
        ->limit(10)
        ->get()
        ->map(function($employee) {
            return [
                'id' => $employee->id,
                'name' => "{$employee->surname}, {$employee->given_name} {$employee->middle_name}",
                'employee_number' => $employee->employee_number,
                'position' => $employee->position,
                'school' => $employee->name_of_school
            ];
        });

        if ($request->expectsJson()) {
            return response()->json($employees);
        }

        return view('leave.teaching.search', compact('employees', 'searchTerm'));
    }
}