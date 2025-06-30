<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('teaching', function (Blueprint $table) {
            $table->id();
            $table->string('surname');
            $table->string('given_name');
            $table->string('middle_name');
            $table->string('sex');
            $table->string('civil_status');
            $table->string('date_of_birth')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->string('position')->nullable();
            $table->string('name_of_school')->nullable();
            $table->string('permanency')->nullable();
            $table->integer('employee_number')->default(0);
            $table->decimal('salary', 5, 2)->default(0);
            $table->integer('leave_credits')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('teaching');
    }
};