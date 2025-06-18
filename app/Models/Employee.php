<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'division', 'designation', 'salary',
        'balance_forwarded_vl', 'balance_forwarded_sl'
    ];

    public function leaveApplications()
    {
        return $this->hasMany(LeaveApplication::class);
    }
}