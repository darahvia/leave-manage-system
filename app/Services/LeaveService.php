<?php
// app/Services/LeaveService.php
namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveApplication;
use Carbon\Carbon;

class LeaveService
{
    /**
     * Process leave application and calculate balances
     */
    public function processLeaveApplication(Employee $employee, array $leaveData, LeaveApplication $leaveApplication = null)
    {
        $leaveType = strtolower($leaveData['leave_type']);
        $workingDays = $leaveData['working_days'];
        $leaveDate = $leaveData['inclusive_date_start'] ?? $leaveData['date_filed'];

        // For new applications, check if employee has sufficient leave balance
        if (!$leaveApplication && !$this->hasSufficientBalance($employee, $leaveType, $workingDays, $leaveDate)) {
            throw new \Exception("Insufficient {$leaveType} balance. Available: " .
                $this->getAvailableBalanceAtDate($employee, $leaveType, $leaveDate) . " days");
        }

        if ($leaveApplication) {
            // Update existing leave
            $leaveApplication->update([
                'leave_type' => $leaveData['leave_type'],
                'leave_details' => $leaveData['leave_details'] ?? null,
                'working_days' => $workingDays,
                'inclusive_date_start' => $leaveData['inclusive_date_start'] ?? null,
                'inclusive_date_end' => $leaveData['inclusive_date_end'] ?? null,
                'date_filed' => $leaveData['date_filed'],
                'commutation' => $leaveData['commutation'] ?? null,
            ]);
        } else {
            // Create new leave
            $leaveApplication = LeaveApplication::create([
                'employee_id' => $employee->id,
                'leave_type' => $leaveData['leave_type'],
                'leave_details' => $leaveData['leave_details'] ?? null,
                'working_days' => $workingDays,
                'inclusive_date_start' => $leaveData['inclusive_date_start'] ?? null,
                'inclusive_date_end' => $leaveData['inclusive_date_end'] ?? null,
                'date_filed' => $leaveData['date_filed'],
                'commutation' => $leaveData['commutation'] ?? null,
            ]);
        }

        // Handle different leave type deductions
        $this->recalculateBalancesFromDate($employee, $leaveDate);

        $this->processLeaveDeductions($employee, $leaveType, $workingDays, $leaveDate);

        return $leaveApplication;
    }

    /**
     * Process leave deductions based on leave type
     */
    private function processLeaveDeductions(Employee $employee, string $leaveType, int $workingDays, $leaveDate)
    {
        switch ($leaveType) {
            case 'vl':
                // Recalculate VL balances from the affected date onwards
                $this->recalculateBalancesFromDate($employee, $leaveDate);
                break;
                
            case 'sl':
                // Recalculate SL balances from the affected date onwards
                $this->recalculateBalancesFromDate($employee, $leaveDate);
                break;
                
            case 'spl':
                // Deduct from SPL balance
                $employee->deductLeave('spl', $workingDays);
                break;
            case 'fl':
                // Force Leave: Deduct from both FL and VL
                $employee->deductLeave('fl', $workingDays);
                $employee->deductLeave('vl', $workingDays); 
                // Also recalculate VL balances since FL affects VL too
                $this->recalculateBalancesFromDate($employee, $leaveDate);
                break;
                
            case 'solo parent':
                $employee->deductLeave('solo_parent', $workingDays);
                break;
                
            case 'ml':
                $employee->deductLeave('ml', $workingDays);
                break;
                
            case 'pl':
                $employee->deductLeave('pl', $workingDays);
                break;
                
            case 'ra9710':
                $employee->deductLeave('ra9710', $workingDays);
                break;
                
            case 'rl':
                $employee->deductLeave('rl', $workingDays);
                break;
                
            case 'sel':
                $employee->deductLeave('sel', $workingDays);
                break;
                
            case 'study_leave':
                $employee->deductLeave('study_leave', $workingDays);
                break;
            case 'vawc':
                $employee->deductLeave('vawc', $workingDays);
                break;
                
            default:
                // Handle other leave types or throw exception
                throw new \Exception("Unknown leave type: {$leaveType}");
        }
    }

    /**
     * Deduct from current VL balance (for Force Leave)
     */
    private function deductFromCurrentVL(Employee $employee, int $workingDays)
    {
        $latestApplication = $employee->leaveApplications()
            ->orderBy('inclusive_date_start', 'desc')
            ->orderBy('earned_date', 'desc')
            ->orderBy('date_filed', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if ($latestApplication) {
            $currentVL = max(0, $latestApplication->current_vl - $workingDays);
            $latestApplication->update(['current_vl' => $currentVL]);
        }
    }

    /**
     * Enhanced balance checking with proper validations
     */
    private function hasSufficientBalance(Employee $employee, string $leaveType, int $workingDays, $atDate = null)
    {
        $availableBalance = $atDate
            ? $this->getAvailableBalanceAtDate($employee, $leaveType, $atDate)
            : $this->getAvailableBalance($employee, $leaveType);
        
        // Special case for Force Leave - check both FL and VL balances
        if ($leaveType === 'fl') {
            $flBalance = $employee->getCurrentLeaveBalance('fl');
            $vlBalance = $atDate 
                ? $this->getAvailableBalanceAtDate($employee, 'vl', $atDate)
                : $this->getAvailableBalance($employee, 'vl');
            
            return ($flBalance >= $workingDays) && ($vlBalance >= $workingDays);
        }
        
        return $availableBalance >= $workingDays;
    }

    /**
     * Get available balance for a specific leave type at a specific date
     */
    private function getAvailableBalanceAtDate(Employee $employee, string $leaveType, $atDate)
    {
        if (in_array($leaveType, ['vl', 'sl'])) {
            $balances = $this->getBalancesBeforeDate($employee, $atDate);
            return $balances[$leaveType] ?? 0;
        }

        // For other leave types, use current balance from employee model
        return $employee->getCurrentLeaveBalance($leaveType);
    }

    /**
     * Recalculate all VL/SL balances from a specific date onwards
     */
    private function recalculateBalancesFromDate(Employee $employee, $fromDate)
    {
        // Get all leave applications (including credits) from the specified date onwards
        $leaves = LeaveApplication::where('employee_id', $employee->id)
            ->where(function($query) use ($fromDate) {
                $query->whereDate('inclusive_date_start', '>=', $fromDate)
                      ->orWhereDate('earned_date', '>=', $fromDate);
            })
            ->get()
            ->sortBy(function($leave) {
                // Create a sortable date - use the earliest relevant date for each record
                $dates = array_filter([
                    $leave->inclusive_date_start,
                    $leave->earned_date,
                    $leave->date_filed
                ]);
               
                if (empty($dates)) {
                    return now(); // fallback if no dates
                }
               
                $earliestDate = min($dates);
               
                // Return a combination of date and ID for consistent sorting
                return $earliestDate . '-' . str_pad($leave->id, 10, '0', STR_PAD_LEFT);
            });

        // Get the balance just before this date
        $balances = $this->getBalancesBeforeDate($employee, $fromDate);

        // Recalculate each leave application's current_vl and current_sl
    foreach ($leaves as $leave) {
        if ($leave->is_credit_earned) {
            // Add earned credits
            $balances['vl'] += $leave->earned_vl ?? 1.25;
            $balances['sl'] += $leave->earned_sl ?? 1.25;
        } else {
            // Handle leave deduction or cancellation credit restoration
            $leaveType = strtolower($leave->leave_type);
            $workingDays = $leave->working_days;
            
            if ($leaveType === 'vl') {
                if ($leave->is_cancellation ?? false) {
                    // Cancellation: add credits back (working_days is negative for cancellations)
                    $balances['vl'] += abs($workingDays);
                } else {
                    // Regular leave: deduct credits
                    $balances['vl'] = max(0, $balances['vl'] - $workingDays);
                }
            } elseif ($leaveType === 'sl') {
                if ($leave->is_cancellation ?? false) {
                    // Cancellation: add credits back (working_days is negative for cancellations)
                    $balances['sl'] += abs($workingDays);
                } else {
                    // Regular leave: deduct credits
                    $balances['sl'] = max(0, $balances['sl'] - $workingDays);
                }
            } elseif ($leaveType === 'fl') {
                // Force Leave also deducts from VL
                $balances['vl'] = max(0, $balances['vl'] - $workingDays);
            }
        }

            // Update the leave application with new balances
            $leave->update([
                'current_vl' => $balances['vl'],
                'current_sl' => $balances['sl'],
            ]);
        }
    }

    /**
     * Get VL/SL balances just before a specific date
     */
    private function getBalancesBeforeDate(Employee $employee, $beforeDate)
    {
        // Get all leave applications before the specified date
        $leaves = LeaveApplication::where('employee_id', $employee->id)
            ->where(function($query) use ($beforeDate) {
                $query->whereDate('inclusive_date_start', '<', $beforeDate)
                    ->orWhereDate('earned_date', '<', $beforeDate);
            })
            ->get()
            ->sortBy(function($leave) {
                // Create a sortable date - use the earliest relevant date for each record
                $dates = array_filter([
                    $leave->inclusive_date_start,
                    $leave->earned_date,
                    $leave->date_filed
                ]);
               
                if (empty($dates)) {
                    return '1900-01-01'; // very early date for records with no dates
                }
               
                $earliestDate = min($dates);
                return $earliestDate . '-' . str_pad($leave->id, 10, '0', STR_PAD_LEFT);
            });

        // Start with forwarded balances
        $balances = [
            'vl' => $employee->balance_forwarded_vl ?? 0,
            'sl' => $employee->balance_forwarded_sl ?? 0,
        ];

        // Apply all leave applications chronologically
        foreach ($leaves as $leave) {
            if ($leave->is_credit_earned) {
                $balances['vl'] += $leave->earned_vl ?? 1.25;
                $balances['sl'] += $leave->earned_sl ?? 1.25;
            } else {
                $leaveType = strtolower($leave->leave_type);
                if ($leaveType === 'vl') {
                    $balances['vl'] = max(0, $balances['vl'] - $leave->working_days);
                } elseif ($leaveType === 'sl') {
                    $balances['sl'] = max(0, $balances['sl'] - $leave->working_days);
                } elseif ($leaveType === 'fl') {
                    // Force Leave also deducts from VL
                    $balances['vl'] = max(0, $balances['vl'] - $leave->working_days);
                }
            }
        }

        return $balances;
    }

    /**
     * Get balance before a specific leave (for editing purposes)
     */
    public function getBalanceBeforeLeave(Employee $employee, LeaveApplication $leaveToEdit, $type = 'vl')
    {
        $leaveDate = $leaveToEdit->inclusive_date_start ?? $leaveToEdit->date_filed;
       
        // Get all leave applications before this one (by date, not ID)
        $leaves = LeaveApplication::where('employee_id', $employee->id)
            ->where('id', '!=', $leaveToEdit->id) // Exclude the leave being edited
            ->where(function($query) use ($leaveDate) {
                $query->where('inclusive_date_start', '<', $leaveDate)
                      ->orWhere('earned_date', '<', $leaveDate);
            })
            ->where(function($query) use ($type) {
                $query->where('leave_type', $type)
                      ->orWhere('is_credit_earned', true);
            })
            ->orderBy('inclusive_date_start')
            ->orderBy('earned_date')
            ->orderBy('date_filed')
            ->orderBy('id')
            ->get();

        // Start with forwarded balance
        $balance = $type === 'vl' ? $employee->balance_forwarded_vl : $employee->balance_forwarded_sl;

        foreach ($leaves as $leave) {
            if ($leave->is_credit_earned) {
                $balance += $type === 'vl' ? ($leave->earned_vl ?? 1.25) : ($leave->earned_sl ?? 1.25);
            } else {
                $leaveType = strtolower($leave->leave_type);
                if ($leaveType === $type) {
                    $balance -= $leave->working_days ?? 0;
                } elseif ($leaveType === 'fl' && $type === 'vl') {
                    // Force Leave also deducts from VL
                    $balance -= $leave->working_days ?? 0;
                }
            }
        }

        return max(0, $balance);
    }

    /**
     * Get current balances for all leave types
     */
    public function getCurrentBalances(Employee $employee)
    {
        $lastApplication = $employee->leaveApplications()
            ->orderBy('inclusive_date_start', 'desc')
            ->orderBy('earned_date', 'desc')
            ->orderBy('date_filed', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return [
            'vl' => $lastApplication ? $lastApplication->current_vl : $employee->balance_forwarded_vl,
            'sl' => $lastApplication ? $lastApplication->current_sl : $employee->balance_forwarded_sl,
            'spl' => $employee->spl,
            'fl' => $employee->fl,
            'solo_parent' => $employee->solo_parent,
            'ml' => $employee->ml,
            'pl' => $employee->pl,
            'ra9710' => $employee->ra9710,
            'rl' => $employee->rl,
            'sel' => $employee->sel,
            'study_leave' => $employee->study_leave,
            'vawc' => $employee->vawc,

        ];
    }

    /**
     * Get available balance for a specific leave type (current balance)
     */
    private function getAvailableBalance(Employee $employee, string $leaveType)
    {
        return $employee->getCurrentLeaveBalance($leaveType);
    }

    /**
     * Add credits earned (monthly leave credits)
     */
    public function addCreditsEarned(Employee $employee, $earnedDate, $vlCredits = 1.25, $slCredits = 1.25)
    {
        $leaveApplication = LeaveApplication::create([
            'employee_id' => $employee->id,
            'is_credit_earned' => true,
            'earned_date' => $earnedDate,
            'earned_vl' => $vlCredits,
            'earned_sl' => $slCredits,
        ]);

        // Recalculate balances from this date onwards
        $this->recalculateBalancesFromDate($employee, $earnedDate);

        return $leaveApplication;
    }

    /**
     * Delete a leave application and recalculate balances
     */
    public function deleteLeaveApplication(LeaveApplication $leaveApplication)
    {
        $employee = $leaveApplication->employee;
        $leaveDate = $leaveApplication->inclusive_date_start ?? $leaveApplication->earned_date ?? $leaveApplication->date_filed;
        $leaveType = strtolower($leaveApplication->leave_type ?? '');
        $workingDays = $leaveApplication->working_days ?? 0;
        
        // If it's a non-VL/SL leave type, restore the balance back to employee model
        if (!in_array($leaveType, ['vl', 'sl']) && !$leaveApplication->is_credit_earned) {
            $this->restoreLeaveBalance($employee, $leaveType, $workingDays);
        }
       
        // Delete the leave application
        $leaveApplication->delete();
       
        // Recalculate VL/SL balances from this date onwards
        if (in_array($leaveType, ['vl', 'sl', 'fl']) || $leaveApplication->is_credit_earned) {
            $this->recalculateBalancesFromDate($employee, $leaveDate);
        }
    }

    /**
     * Restore leave balance when deleting a leave application
     */
    private function restoreLeaveBalance(Employee $employee, string $leaveType, int $workingDays)
    {
        switch ($leaveType) {
            case 'spl':
                $employee->spl = ($employee->spl ?? 0) + $workingDays;
                break;
            case 'fl':
                $employee->fl = ($employee->fl ?? 0) + $workingDays;
                break;
            case 'solo_parent':
                $employee->solo_parent = ($employee->solo_parent ?? 0) + $workingDays;
                break;
            case 'ml':
                $employee->ml = ($employee->ml ?? 0) + $workingDays;
                break;
            case 'pl':
                $employee->pl = ($employee->pl ?? 0) + $workingDays;
                break;
            case 'ra9710':
                $employee->ra9710 = ($employee->ra9710 ?? 0) + $workingDays;
                break;
            case 'rl':
                $employee->rl = ($employee->rl ?? 0) + $workingDays;
                break;
            case 'sel':
                $employee->sel = ($employee->sel ?? 0) + $workingDays;
                break;
            case 'study_leave':
                $employee->study_leave = ($employee->study_leave ?? 0) + $workingDays;
                break;
            case 'vawc':
                $employee->vawc = ($employee->vawc ?? 0) + $workingDays;
                break;
        }
        
        $employee->save();
    }

    /**
     * Get leave type display names
     */
    public static function getLeaveTypes()
    {
        return [
            'VL' => 'Vacation Leave',
            'FL' => 'Mandatory/Forced Leave',
            'SL' => 'Sick Leave',
            'ML' => 'Maternity Leave',
            'PL' => 'Paternity Leave',
            'SPL' => 'Special Privilege Leave',
            'SOLO_PARENT' => 'Solo Parent Leave',
            'STUDY_LEAVE' => 'Study Leave',
            'VAWC' => '10-Day VAWC Leave',
            'RL' => 'Rehabilitation Privilege',
            'RA9710' => 'Special Leave Benefits for Women',
            'SEL' => 'Special Emergency Leave',
            'ADOPT' => 'Adoption Leave',
        ];
    }
    public function processCancellation(Employee $employee, array $cancellationData)
    {
        $leaveType = strtolower($cancellationData['leave_type']);
        $workingDays = $cancellationData['working_days']; // Credits to restore
        $cancellationDate = $cancellationData['date_filed']; // Date cancellation was filed
        $effectiveDate = $cancellationData['inclusive_date_start']; // When cancellation takes effect

        // Create a new leave application record for the cancellation
        // This will appear as a new row in the table
        $cancellationApplication = LeaveApplication::create([
            'employee_id' => $employee->id,
            'leave_type' => $cancellationData['leave_type'],
            'leave_details' => 'CANCELLED - Credits Restored',
            'working_days' => -$workingDays, // Negative value to indicate credit restoration
            'inclusive_date_start' => $effectiveDate,
            'inclusive_date_end' => $cancellationData['inclusive_date_end'],
            'date_filed' => $cancellationDate,
            'commutation' => null,
            'is_cancellation' => true, // Mark as cancellation entry
        ]);

        // Recalculate all balances from the effective date onwards
        $this->recalculateBalancesFromDate($employee, $effectiveDate);

        // For non-VL/SL leave types, add credits back to employee balance
        if (!in_array($leaveType, ['vl', 'sl'])) {
            $employee->addLeave($leaveType, $workingDays); // Add credits back
        }

        return $cancellationApplication;
    }
}
