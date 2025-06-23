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

        // Recalculate ALL balances for this employee (complete recalculation)
        $this->recalculateBalancesFromDate($employee);

        // Update employee balances for non-VL/SL leave types
        if (!in_array($leaveType, ['vl', 'sl'])) {
            $employee->deductLeave($leaveType, $workingDays);
        }

        return $leaveApplication;
    }

    /**
     * Recalculate ALL VL/SL balances starting from forwarded balances
     * This ensures complete accuracy when leaves are added/edited in the middle of timeline
     */
    private function recalculateBalancesFromDate(Employee $employee, $fromDate = null)
    {
        // Get ALL leave applications (including credits) for this employee, ordered chronologically
        // Order by the actual occurrence date for proper balance calculation
        $leaves = LeaveApplication::where('employee_id', $employee->id)
            ->orderByRaw('COALESCE(earned_date, inclusive_date_start, date_filed) ASC')
            ->orderBy('id') // Secondary sort for consistent ordering when dates are same
            ->get();

        // Start with forwarded balances (beginning of year balances)
        $balances = [
            'vl' => $employee->balance_forwarded_vl ?? 0,
            'sl' => $employee->balance_forwarded_sl ?? 0,
        ];

        // Recalculate each leave application's current_vl and current_sl from the beginning
        foreach ($leaves as $leave) {
            if ($leave->is_credit_earned) {
                // Add earned credits (based on earned_date)
                $balances['vl'] += $leave->earned_vl ?? 1.25;
                $balances['sl'] += $leave->earned_sl ?? 1.25;
            } else {
                // Deduct leave (based on inclusive_date_start - when leave actually occurred)
                $leaveType = strtolower($leave->leave_type);
                if ($leaveType === 'vl') {
                    $balances['vl'] = max(0, $balances['vl'] - $leave->working_days);
                } elseif ($leaveType === 'sl') {
                    $balances['sl'] = max(0, $balances['sl'] - $leave->working_days);
                }
            }

            // Update the leave application with new running balances
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
        // Find the last leave application before the specified date
        $lastLeave = LeaveApplication::where('employee_id', $employee->id)
            ->where(function($query) use ($beforeDate) {
                $query->where('inclusive_date_start', '<', $beforeDate)
                      ->orWhere('earned_date', '<', $beforeDate);
            })
            ->orderBy('inclusive_date_start', 'desc')
            ->orderBy('earned_date', 'desc')
            ->orderBy('date_filed', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if ($lastLeave) {
            return [
                'vl' => $lastLeave->current_vl ?? $employee->balance_forwarded_vl,
                'sl' => $lastLeave->current_sl ?? $employee->balance_forwarded_sl,
            ];
        }

        // No leave applications before this date, use forwarded balances
        return [
            'vl' => $employee->balance_forwarded_vl,
            'sl' => $employee->balance_forwarded_sl,
        ];
    }

    /**
     * Get available balance for a specific leave type at a specific date
     */
    private function getAvailableBalanceAtDate(Employee $employee, string $leaveType, $atDate)
    {
        if (in_array($leaveType, ['vl', 'sl'])) {
            // Calculate balance up to the specified date
            $leaves = LeaveApplication::where('employee_id', $employee->id)
                ->where(function($query) use ($atDate) {
                    $query->where('inclusive_date_start', '<', $atDate)
                          ->orWhere('earned_date', '<', $atDate);
                })
                ->orderByRaw('COALESCE(earned_date, inclusive_date_start, date_filed) ASC')
                ->get();

            $balance = $leaveType === 'vl' ? $employee->balance_forwarded_vl : $employee->balance_forwarded_sl;

            foreach ($leaves as $leave) {
                if ($leave->is_credit_earned) {
                    $balance += $leaveType === 'vl' ? ($leave->earned_vl ?? 1.25) : ($leave->earned_sl ?? 1.25);
                } else {
                    $currentLeaveType = strtolower($leave->leave_type);
                    if ($currentLeaveType === $leaveType) {
                        $balance -= $leave->working_days;
                    }
                }
            }

            return max(0, $balance);
        }

        // For other leave types, use current balance from employee model
        return $employee->getCurrentLeaveBalance($leaveType);
    }

    /**
     * Check if employee has sufficient leave balance at a specific date
     */
    private function hasSufficientBalance(Employee $employee, string $leaveType, int $workingDays, $atDate = null)
    {
        $availableBalance = $atDate 
            ? $this->getAvailableBalanceAtDate($employee, $leaveType, $atDate)
            : $this->getAvailableBalance($employee, $leaveType);
            
        return $availableBalance >= $workingDays;
    }

    /**
     * Get balance before a specific leave (for editing purposes)
     */
    public function getBalanceBeforeLeave(Employee $employee, LeaveApplication $leaveToEdit, $type = 'vl')
    {
        $leaveDate = $leaveToEdit->inclusive_date_start ?? $leaveToEdit->date_filed;
        
        // Get all leave applications before this one (by occurrence date, not ID)
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
            ->orderByRaw('COALESCE(earned_date, inclusive_date_start, date_filed) ASC')
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
                }
            }
        }

        return max(0, $balance);
    }

    /**
     * Get current balances for all leave types
     */
    private function getCurrentBalances(Employee $employee)
    {
        $lastApplication = $employee->leaveApplications()
            ->orderByRaw('COALESCE(earned_date, inclusive_date_start, date_filed) DESC')
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

        // Recalculate ALL balances from the beginning
        $this->recalculateBalancesFromDate($employee);

        return $leaveApplication;
    }

    /**
     * Delete a leave application and recalculate balances
     */
    public function deleteLeaveApplication(LeaveApplication $leaveApplication)
    {
        $employee = $leaveApplication->employee;
        $leaveDate = $leaveApplication->inclusive_date_start ?? $leaveApplication->earned_date ?? $leaveApplication->date_filed;
        
        // Delete the leave application
        $leaveApplication->delete();
        
        // Recalculate ALL balances from the beginning
        $this->recalculateBalancesFromDate($employee);
    }

    /**
     * Get leave type display names
     */
    public static function getLeaveTypes()
    {
        return [
            'VL' => 'Vacation Leave',
            'SL' => 'Sick Leave',
            'SPL' => 'Special Privilege Leave',
            'FL' => 'Forced Leave',
            'SOLO_PARENT' => 'Solo Parent Leave',
            'ML' => 'Maternity Leave',
            'PL' => 'Paternity Leave',
            'RA9710' => 'RA 9710 Leave',
            'RL' => 'Rehabilitation Leave',
            'SEL' => 'Special Emergency Leave',
            'STUDY_LEAVE' => 'Study Leave',
        ];
    }
}