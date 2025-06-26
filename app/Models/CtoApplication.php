<?php
// app/Models/CtoApplication.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory; // <-- THIS LINE IS FIXED (no '->' here)
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class CtoApplication extends Model
{
    use HasFactory;

    protected $table = 'cto_applications';

    protected $fillable = [
        'employee_id',
        'special_order',
        'date_of_activity_start',
        'date_of_activity_end',
        'activity',
        'credits_earned',
        'date_of_absence_start',
        'date_of_absence_end',
        'no_of_days',
        'balance',
        'is_activity',
    ];

    protected $casts = [
        'date_of_activity_start' => 'date',
        'date_of_activity_end' => 'date',
        'date_of_absence_start' => 'date',
        'date_of_absence_end' => 'date',
        'credits_earned' => 'decimal:2',
        'no_of_days' => 'decimal:2',
        'balance' => 'decimal:2',
        'is_activity' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Relationship: If this is an activity, it can be used by many absences.
     */
    public function creditUsages()
    {
        return $this->hasMany(CtoCreditUsage::class, 'cto_activity_id');
    }

    /**
     * Relationship: If this is an absence, it consumes credits from many activities.
     */
    public function consumedActivities()
    {
        return $this->hasMany(CtoCreditUsage::class, 'cto_absence_id');
    }

    // Accessor for formatted activity date
    public function getFormattedActivityDateAttribute()
    {
        if (!$this->date_of_activity_start) {
            return '';
        }

        $start = Carbon::parse($this->date_of_activity_start)->format('n/j/Y');
        
        if ($this->date_of_activity_end && $this->date_of_activity_start != $this->date_of_activity_end) {
            $end = Carbon::parse($this->date_of_activity_end)->format('n/j/Y');
            return $start . ' - ' . $end;
        }
        
        return $start;
    }

    // Accessor for formatted absence date
    public function getFormattedAbsenceDateAttribute()
    {
        if (!$this->date_of_absence_start) {
            return '';
        }

        $start = Carbon::parse($this->date_of_absence_start)->format('n/j/Y');
        
        if ($this->date_of_absence_end && $this->date_of_absence_start != $this->date_of_absence_end) {
            $end = Carbon::parse($this->date_of_absence_end)->format('n/j/Y');
            return $start . ' - ' . $end;
        }
        
        return $start;
    }

    /**
     * Get the effective date for chronological sorting (activity start date or absence start date).
     */
    public function getEffectiveDateAttribute()
    {
        if ($this->is_activity) {
            return $this->date_of_activity_start;
        }
        return $this->date_of_absence_start;
    }

    /**
     * Calculate remaining credits for an activity.
     * This will be used by the FIFO deduction logic.
     */
    public function getRemainingCreditsAttribute(): float
    {
        if (!$this->is_activity) {
            return 0.0; // Only activities have remaining credits
        }
        // Sum up all usages linked to this activity
        $totalUsed = $this->creditUsages()->sum('days_used');
        return $this->credits_earned - $totalUsed;
    }

    /**
     * Determine if an activity is expired based on a specific check date.
     * Credits expire 1 year AFTER the end date of the activity.
     * @param Carbon $checkDate The date against which to check expiration (e.g., absence start date or current record's effective date).
     */
    public function isExpiredAt(Carbon $checkDate): bool
    {
        if (!$this->is_activity || !$this->date_of_activity_end) {
            return false; // Only activities with an end date can expire this way
        }
        // Calculate the exact expiration date (1 year after activity end date)
        $expirationDate = $this->date_of_activity_end->copy()->addYear();
        
        // Return true if the checkDate is on or after the expirationDate
        return $checkDate->greaterThanOrEqualTo($expirationDate);
    }

    /**
     * Convenience method to check if the activity is expired as of the current date/time.
     */
    public function isExpired(): bool
    {
        return $this->isExpiredAt(Carbon::now());
    }
}