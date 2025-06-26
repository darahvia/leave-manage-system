<?php
// app/Services/CtoService.php
namespace App\Services;

use App\Models\Employee;
use App\Models\CtoApplication;
use App\Models\CtoCreditUsage; // Corrected import
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // Corrected import

class CtoService
{
    /**
     * Process CTO activity (credits earned).
     * The balance will be recalculated after the record is created/updated.
     */
    public function processCtoActivity(Employee $employee, array $activityData, CtoApplication $existingRecord = null)
    {
        DB::beginTransaction();
        try {
            if ($existingRecord) {
                $existingRecord->update([
                    'special_order' => $activityData['special_order'],
                    'date_of_activity_start' => $activityData['date_of_activity_start'],
                    'date_of_activity_end' => $activityData['date_of_activity_end'],
                    'activity' => $activityData['activity'],
                    'credits_earned' => $activityData['credits_earned'],
                    'no_of_days' => 0, // Ensure this is 0 for activities
                    'is_activity' => true,
                    // Balance is set by recalculation method
                ]);
                $ctoApplication = $existingRecord;
            } else {
                $ctoApplication = new CtoApplication([
                    'employee_id' => $employee->id,
                    'special_order' => $activityData['special_order'],
                    'date_of_activity_start' => $activityData['date_of_activity_start'],
                    'date_of_activity_end' => $activityData['date_of_activity_end'],
                    'activity' => $activityData['activity'],
                    'credits_earned' => $activityData['credits_earned'],
                    'no_of_days' => 0,
                    'is_activity' => true,
                    'balance' => 0, // Temporarily set, will be updated by recalculation
                ]);
                $ctoApplication->save();
            }
            
            $this->recalculateBalancesForEmployee($employee); // Recalculate all balances

            DB::commit();
            return $ctoApplication;
        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Error in processCtoActivity: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * Process CTO usage (credits deducted) with FIFO and expiration.
     */
    public function processCtoUsage(Employee $employee, array $usageData, CtoApplication $existingRecord = null)
    {
        $absenceDaysNeeded = $usageData['no_of_days'];
        $absenceStartDate = Carbon::parse($usageData['date_of_absence_start']);

        DB::beginTransaction();
        try {
            // If updating an existing absence, first clear its previous usages
            // This prevents double-deduction and allows reallocation of credits if dates/days change
            if ($existingRecord) {
                CtoCreditUsage::where('cto_absence_id', $existingRecord->id)->delete();
            }

            // Get all non-expired, available credits, sorted oldest first (FIFO)
            // Eager load creditUsages for each activity to correctly calculate remaining_credits
            $availableActivities = $employee->ctoApplications()
                ->where('is_activity', true) // Only consider credit-earning activities
                ->with('creditUsages') // Crucial for `remaining_credits` accessor
                ->get() 
                ->filter(function($activity) use ($absenceStartDate) {
                    // Filter out activities that have expired by the absence start date
                    return !$activity->isExpiredAt($absenceStartDate);
                })
                ->sortBy('effective_date') // Sort by the activity date to ensure FIFO
                ->values(); // Re-index the collection after sorting/filtering

            // Calculate the total eligible credits available for this absence
            $totalEligibleCredits = $availableActivities->sum(function($activity) {
                return $activity->remaining_credits;
            });

            // Check if there are enough eligible credits before proceeding with record creation
            if ($totalEligibleCredits < $absenceDaysNeeded) {
                DB::rollback(); // Rollback any cleared usages if updating
                throw new \Exception('Insufficient eligible CTO balance. Current eligible: ' . $totalEligibleCredits . ' days, Required: ' . $absenceDaysNeeded . ' days');
            }

            // Create or update the CTO absence record itself
            if ($existingRecord) {
                $existingRecord->update([
                    'date_of_absence_start' => $usageData['date_of_absence_start'],
                    'date_of_absence_end' => $usageData['date_of_absence_end'],
                    'no_of_days' => $usageData['no_of_days'],
                    'credits_earned' => 0, // No credits earned for usage
                    'is_activity' => false,
                    'balance' => 0, // Temporarily set, will be updated by recalculation
                ]);
                $ctoAbsence = $existingRecord;
            } else {
                $ctoAbsence = new CtoApplication([
                    'employee_id' => $employee->id,
                    'date_of_absence_start' => $usageData['date_of_absence_start'],
                    'date_of_absence_end' => $usageData['date_of_absence_end'],
                    'no_of_days' => $usageData['no_of_days'],
                    'credits_earned' => 0,
                    'is_activity' => false,
                    'balance' => 0, // Temporarily set, will be updated by recalculation
                ]);
                $ctoAbsence->save();
            }

            // Deduct days from activities based on FIFO and create CtoCreditUsage records
            $remainingDaysToDeduct = $absenceDaysNeeded;
            foreach ($availableActivities as $activity) {
                if ($remainingDaysToDeduct <= 0) break; // Stop if all absence days are covered

                $creditsFromThisActivity = $activity->remaining_credits;
                $daysToUseFromThisActivity = min($remainingDaysToDeduct, $creditsFromThisActivity);

                if ($daysToUseFromThisActivity > 0) {
                    CtoCreditUsage::create([
                        'cto_activity_id' => $activity->id,
                        'cto_absence_id' => $ctoAbsence->id,
                        'days_used' => $daysToUseFromThisActivity,
                    ]);
                    $remainingDaysToDeduct -= $daysToUseFromThisActivity;
                }
            }
            
            // CRITICAL: Recalculate all balances after the changes
            $this->recalculateBalancesForEmployee($employee);

            DB::commit();
            return $ctoAbsence;
        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Error in processCtoUsage (FIFO deduction): " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * Recalculate and update the 'balance' for all CTO applications for an employee in chronological order,
     * considering FIFO deduction and expiration rules to reflect the eligible running balance.
     * This is the core method that defines the historical running balance column in the table.
     */
    public function recalculateBalancesForEmployee(Employee $employee): void
    {
        // Load all CTO applications with their usage/consumed relationships
        // This is crucial for determining remaining credits of activities and days consumed by absences
        $ctoApplications = $employee->ctoApplications()
            ->with(['creditUsages', 'consumedActivities']) // Eager load relationships
            ->get()
            ->sortBy(function($cto) {
                // Ensure strict chronological order. Prioritize activities on the same date.
                return $cto->effective_date->timestamp . ($cto->is_activity ? '0' : '1');
            })
            ->values();

        // This pool represents credits that are currently 'active' and not expired/consumed
        // Key: cto_activity_id, Value: ['earned_amount', 'date_of_activity_end', 'remaining']
        // 'remaining' in this pool is the amount still available from that specific activity
        $activeCreditPools = collect(); 

        $runningEligibleBalance = 0.0;

        Log::info("--- START RECALCULATION FOR EMPLOYEE ID: " . $employee->id . " ---");
        Log::info("Initial State: Running Eligible Balance = " . $runningEligibleBalance);
        Log::info("Total CTO Applications to process: " . $ctoApplications->count());

        foreach ($ctoApplications as $cto) {
            Log::info("Processing Record ID: " . $cto->id . 
                      " | Type: " . ($cto->is_activity ? "Activity (Earned " . $cto->credits_earned . ")" : "Absence (Used " . $cto->no_of_days . ")") .
                      " | Date: " . $cto->effective_date->toDateString() .
                      " | Balance before transaction & expiry check: " . $runningEligibleBalance);
            Log::info("  Active Credits Pool before this transaction:", $activeCreditPools->toArray());

            // --- Step 1: Check for and process EXPIRED credits from the active pool ---
            // Iterate over a copy of keys to safely remove items from activeCreditPools during iteration
            foreach ($activeCreditPools->keys() as $activityIdInPool) {
                // Check if the item still exists in the pool (might have been removed by a previous iteration if ID collision occurs, though unlikely here)
                if (!$activeCreditPools->has($activityIdInPool)) { 
                    continue;
                }

                $activityData = $activeCreditPools->get($activityIdInPool);
                $expiryDate = $activityData['date_of_activity_end']->copy()->addYear(); // Calculate expiry for this activity

                Log::info("    Checking Pool Activity ID: " . $activityIdInPool . 
                          " | Expiry Date: " . $expiryDate->toDateString() .
                          " | Remaining in Pool: " . $activityData['remaining'] .
                          " | Current Transaction Date: " . $cto->effective_date->toDateString() .
                          " | Is Expired At Current Transaction Date: " . ($cto->effective_date->greaterThanOrEqualTo($expiryDate) ? 'YES' : 'NO'));

                // If the activity's expiry date is before or on the effective date of the current transaction, it expires.
                if ($cto->effective_date->greaterThanOrEqualTo($expiryDate)) {
                    $amountToDeduct = $activityData['remaining']; // Deduct whatever is left in this pool item
                    if ($amountToDeduct > 0) { // Only deduct if there's something left
                        $runningEligibleBalance -= $amountToDeduct;
                        Log::info("      -> EXPIRED: Deducting " . $amountToDeduct . " from balance. Balance now: " . $runningEligibleBalance);
                    } else {
                        Log::info("      -> EXPIRED: Activity had 0 remaining, no deduction from balance.");
                    }
                    $activeCreditPools->forget($activityIdInPool); // Remove from active pool
                }
            }
            Log::info("  Active Credits Pool after expiry check:", $activeCreditPools->toArray());

            // --- Step 2: Process the current CTO transaction (Activity or Absence) ---
            if ($cto->is_activity) {
                $runningEligibleBalance += $cto->credits_earned;
                Log::info("  Processing Activity (ID: " . $cto->id . "): Added " . $cto->credits_earned . ". Balance now: " . $runningEligibleBalance);
                
                // Add this new activity to the pool of eligible credits if it's not immediately expired
                if (!$cto->isExpiredAt($cto->effective_date)) { 
                    $activeCreditPools->put($cto->id, [
                        'earned_amount' => $cto->credits_earned,
                        'date_of_activity_end' => $cto->date_of_activity_end,
                        'remaining' => $cto->credits_earned, // Initial remaining amount for this activity in the pool
                    ]);
                    Log::info("  Activity ID " . $cto->id . " added to active pool. Pool:", $activeCreditPools->toArray());
                } else {
                    Log::info("  Activity ID " . $cto->id . " earned but immediately expired. Not added to active pool.");
                }

            } else { // This is an absence
                $deductedAmount = $cto->no_of_days;
                $runningEligibleBalance -= $deductedAmount;
                Log::info("  Processing Absence (ID: " . $cto->id . "): Deducted " . $deductedAmount . ". Balance now: " . $runningEligibleBalance);

                // Reconstruct how this absence historically consumed credits from specific activities.
                // This relies on the `CtoCreditUsage` records associated with this absence.
                foreach ($cto->consumedActivities as $usage) {
                    Log::info("    Absence usage detail (Usage ID: " . $usage->id . "): Consumed " . $usage->days_used . 
                              " from Activity ID: " . $usage->cto_activity_id);
                    
                    if ($activeCreditPools->has($usage->cto_activity_id)) {
                        $activityData = $activeCreditPools->get($usage->cto_activity_id);
                        $activityData['remaining'] -= $usage->days_used; // Deduct from its remaining amount in the pool
                        Log::info("      Pool Activity ID " . $usage->cto_activity_id . " new remaining: " . $activityData['remaining']);
                        
                        if ($activityData['remaining'] <= 0) {
                            $activeCreditPools->forget($usage->cto_activity_id); // Fully consumed, remove from pool
                            Log::info("      Pool Activity ID " . $usage->cto_activity_id . " fully consumed and removed from pool.");
                        } else {
                            $activeCreditPools->put($usage->cto_activity_id, $activityData);
                        }
                    } else {
                        Log::warning("      Historical usage for non-active/expired/unknown credit pool detected (Activity ID: " . $usage->cto_activity_id . "). This may indicate a data discrepancy if this activity should have been active.", [
                            'absence_id' => $cto->id, 
                            'activity_id_in_usage' => $usage->cto_activity_id,
                            'days_used_from_usage' => $usage->days_used
                        ]);
                    }
                }
                Log::info("  Active Credits Pool after absence consumption:", $activeCreditPools->toArray());
            }

            // --- Step 3: Update the balance field for the current CTO record and save ---
            $newBalanceValue = round($runningEligibleBalance, 2);
            if ($cto->balance !== $newBalanceValue) {
                $cto->balance = $newBalanceValue;
                $cto->save(); // Save to database
                Log::info("  CTO record ID " . $cto->id . " balance updated to: " . $newBalanceValue);
            } else {
                Log::info("  CTO record ID " . $cto->id . " balance unchanged: " . $newBalanceValue);
            }
            Log::info("--- END OF RECORD ID: " . $cto->id . " PROCESSING ---");
        }
        Log::info("Final Recalculation for Employee ID: " . $employee->id . ". Ending Eligible Balance: " . round($runningEligibleBalance, 2));
        Log::info("--- END RECALCULATION FOR EMPLOYEE ID: " . $employee->id . " ---");
    }

    /**
     * Get current *total* CTO balance for an employee (sum of all earned - sum of all used).
     * This does NOT consider expiration or FIFO for the total.
     * Use getEligibleCtoBalance() for checks against new absences.
     */
    public function getCurrentCtoBalance(Employee $employee): float
    {
        $totalEarned = $employee->ctoApplications()
            ->where('is_activity', true)
            ->sum('credits_earned');

        $totalUsed = $employee->ctoApplications()
            ->where('is_activity', false)
            ->sum('no_of_days');

        return $totalEarned - $totalUsed;
    }

    /**
     * Get the *eligible* CTO balance for an employee (non-expired, available credits).
     * This is the balance against which new absences should be checked.
     * @param Employee $employee The employee model.
     * @param Carbon|null $checkDate The date against which to check expiration (defaults to now).
     * @return float The total eligible credits.
     */
    public function getEligibleCtoBalance(Employee $employee, Carbon $checkDate = null): float
    {
        $checkDate = $checkDate ?? Carbon::now();

        $eligibleActivities = $employee->ctoApplications()
            ->where('is_activity', true)
            ->with('creditUsages') // Eager load usages to calculate remaining_credits
            ->get()
            ->filter(function($activity) use ($checkDate) {
                return !$activity->isExpiredAt($checkDate); // Filter out activities expired by checkDate
            });

        $totalEligibleCredits = 0.0;
        foreach ($eligibleActivities as $activity) {
            $totalEligibleCredits += $activity->remaining_credits;
        }

        return $totalEligibleCredits;
    }

    /**
     * Calculate working days between two dates (excluding weekends).
     */
    public function calculateWorkingDays(string $startDate, string $endDate): int
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        $workingDays = 0;
        
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if ($date->dayOfWeek >= 1 && $date->dayOfWeek <= 5) { // Monday to Friday
                $workingDays++;
            }
        }
        
        return $workingDays;
    }

    /**
     * Delete CTO record and trigger balance recalculation.
     */
    public function deleteCtoRecord(CtoApplication $ctoApplication)
    {
        DB::beginTransaction();
        try {
            $employee = $ctoApplication->employee; 

            // If deleting an absence, first clear its linked usages
            if (!$ctoApplication->is_activity) {
                CtoCreditUsage::where('cto_absence_id', $ctoApplication->id)->delete();
            }
            // If deleting an activity, usages referencing it will be cascade deleted by DB constraint
            // Or you could explicitly delete them here: CtoCreditUsage::where('cto_activity_id', $ctoApplication->id)->delete();

            $ctoApplication->delete();
            
            $this->recalculateBalancesForEmployee($employee); // Recalculate balances after deletion
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Error deleting CTO record and recalculating: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }
}