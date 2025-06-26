<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CtoCreditUsage extends Model
{
    use HasFactory;

    protected $table = 'cto_credit_usages';

    protected $fillable = [
        'cto_activity_id',
        'cto_absence_id',
        'days_used',
    ];

    protected $casts = [
        'days_used' => 'decimal:2',
    ];

    public function ctoActivity()
    {
        return $this->belongsTo(CtoApplication::class, 'cto_activity_id');
    }

    public function ctoAbsence()
    {
        return $this->belongsTo(CtoApplication::class, 'cto_absence_id');
    }
}