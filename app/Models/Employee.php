<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'division', 'designation', 'salary', 
        'vl', 'sl', 'spl', 'fl', 'solo_parent', 'ml', 'pl',
        'ra9710', 'rl', 'sel', 'study_leave',
        'balance_forwarded_vl', 'balance_forwarded_sl'
    ];

    public function leaveApplications()
    {
        return $this->hasMany(LeaveApplication::class);
    }
    
    public function getCurrentLeaveBalance($leaveType)
    {
        $lastApplication = $this->leaveApplications()->latest()->first();

        switch (strtolower($leaveType)) {
            case 'vl':
                return $lastApplication ? $lastApplication->current_vl : $this->balance_forwarded_vl;
            case 'sl':
                return $lastApplication ? $lastApplication->current_sl : $this->balance_forwarded_sl;
            case 'spl':
                return $this->spl;
            case 'fl':
                return $this->fl;
            case 'solo_parent':
                return $this->solo_parent;
            case 'ml':
                return $this->ml;
            case 'pl':
                return $this->pl;
            case 'ra9710':
                return $this->ra9710;
            case 'rl':
                return $this->rl;
            case 'sel':
                return $this->sel;
            case 'study_leave':
                return $this->study_leave;
            default:
                return 0;
        }
    }

    /**
     * Deduct leave days from the appropriate leave type
     */
    public function deductLeave($leaveType, $days)
    {
        switch (strtolower($leaveType)) {
            case 'spl':
                $this->spl = max(0, $this->spl - $days);
                break;
            case 'fl':
                $this->fl = max(0, $this->fl - $days);
                break;
            case 'solo_parent':
                $this->solo_parent = max(0, $this->solo_parent - $days);
                break;
            case 'ml':
                $this->ml = max(0, $this->ml - $days);
                break;
            case 'pl':
                $this->pl = max(0, $this->pl - $days);
                break;
            case 'ra9710':
                $this->ra9710 = max(0, $this->ra9710 - $days);
                break;
            case 'rl':
                $this->rl = max(0, $this->rl - $days);
                break;
            case 'sel':
                $this->sel = max(0, $this->sel - $days);
                break;
            case 'study_leave':
                $this->study_leave = max(0, $this->study_leave - $days);
                break;
        }
        
        $this->save();
    }
}