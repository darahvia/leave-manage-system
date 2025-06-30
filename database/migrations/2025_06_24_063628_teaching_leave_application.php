<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('teaching_leave_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('teaching'); // Fixed: Reference 'teaching' table
            $table->date('leave_incurred_date')->nullable();
            $table->integer('leave_incurred_days')->nullable(); // Fixed: Should be integer, not string
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('teaching_leave_applications');
    }
};