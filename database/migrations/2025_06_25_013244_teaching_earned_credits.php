<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('teaching_earned_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('teaching'); // Fixed: Reference 'teaching' table
            $table->string('earned_date')->nullable(); // Fixed: Should be date, not string
            $table->string('special_order')->nullable();
            $table->decimal('days', 8, 2)->default(0); // Fixed: Allow decimal for partial days
            $table->string('reference')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('teaching_earned_credits');
    }
};