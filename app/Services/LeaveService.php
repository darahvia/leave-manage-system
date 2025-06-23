<?php
// app/Services/LeaveService.php
namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveApplication;


class LeaveService
{
    /**
     * Process leave application and calculate balances
     */
    public function processLeaveApplication(Employee $employee, array $leaveData)
    {
        $leaveType = strtolower($leaveData['leave_type']);
        $workingDays = $leaveData['working_days'];

        // Get current balances
        $currentBalances = $this->getCurrentBalances($employee);

        // Check if employee has sufficient leave balance
        if (!$this->hasSufficientBalance($employee, $leaveType, $workingDays)) {
            throw new \Exception("Insufficient {$leaveType} balance. Available: " . 
                $this->getAvailableBalance($employee, $leaveType) . " days");
        }

        // Calculate new balances after deduction
        $newBalances = $this->calculateNewBalances($currentBalances, $leaveType, $workingDays);

        // Create leave application record
        $leaveApplication = LeaveApplication::create([
            'employee_id' => $employee->id,
            'leave_type' => $leaveData['leave_type'],
            'leave_details' => $leaveData['leave_details'] ?? null,
            'working_days' => $workingDays,
            'inclusive_date_start' => $leaveData['inclusive_date_start'] ?? null,
            'inclusive_date_end' => $leaveData['inclusive_date_end'] ?? null,
            'date_filed' => $leaveData['date_filed'],
            'commutation' => $leaveData['commutation'] ?? null,
            'current_vl' => $newBalances['vl'],
            'current_sl' => $newBalances['sl'],
        ]);

        // Update employee balances for non-VL/SL leave types
        if (!in_array($leaveType, ['vl', 'sl'])) {
            $employee->deductLeave($leaveType, $workingDays);
        }

        return $leaveApplication;
    }

    /**
     * Get current balances for all leave types
     */
    private function getCurrentBalances(Employee $employee)
    {
        $lastApplication = $employee->leaveApplications()->latest()->first();

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
     * Calculate new balances after leave deduction
     */
    private function calculateNewBalances(array $currentBalances, string $leaveType, int $workingDays)
    {
        $newBalances = $currentBalances;

        switch ($leaveType) {
            case 'vl':
                $newBalances['vl'] = max(0, $currentBalances['vl'] - $workingDays);
                break;
            case 'sl':
                $newBalances['sl'] = max(0, $currentBalances['sl'] - $workingDays);
                break;
            // For other leave types, VL and SL remain the same
            // The actual deduction happens in the employee model
        }

        return $newBalances;
    }

    /**
     * Check if employee has sufficient leave balance
     */
    private function hasSufficientBalance(Employee $employee, string $leaveType, int $workingDays)
    {
        $availableBalance = $this->getAvailableBalance($employee, $leaveType);
        return $availableBalance >= $workingDays;
    }

    /**
     * Get available balance for a specific leave type
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
        $currentBalances = $this->getCurrentBalances($employee);

        return LeaveApplication::create([
            'employee_id' => $employee->id,
            'current_vl' => $currentBalances['vl'] + $vlCredits,
            'current_sl' => $currentBalances['sl'] + $slCredits,
            'is_credit_earned' => true,
            'earned_date' => $earnedDate,
        ]);
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