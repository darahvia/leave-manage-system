<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeachingEarnedCredits extends Model
{
    use HasFactory;

    protected $table = 'teaching_earned_credits';

    protected $fillable = [
        'employee_id',
        'earned_date',
        'special_order',
        'days',
        'reference',
    ];


    public function teaching()
    {
        return $this->belongsTo(Teaching::class, 'employee_id');
    }
}
