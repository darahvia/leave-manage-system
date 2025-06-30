<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeachingLeaveApplications extends Model
{
    use HasFactory;

    protected $table = 'teaching_leave_applications';

    protected $fillable = [
        'employee_id',
        'leave_incurred_date',
        'leave_incurred_days',
    ];


    public function teaching()
    {
        return $this->belongsTo(Teaching::class, 'employee_id');
    }
}
