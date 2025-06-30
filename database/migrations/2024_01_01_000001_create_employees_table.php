<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('surname');
            $table->string('given_name');
            $table->string('middle_name');
            $table->string('division');
            $table->string('designation');
            $table->string('original_appointment')->nullable();
            $table->string('salary')->nullable();
            $table->integer('vl');
            $table->integer('sl');
            $table->integer('spl');
            $table->integer('fl');
            $table->integer('solo_parent');
            $table->integer('ml');
            $table->integer('pl');
            $table->integer('ra9710');
            $table->integer('rl');
            $table->integer('sel');
            $table->integer('study_leave');
            $table->integer('adopt');
            $table->integer('vawc');
            $table->decimal('balance_forwarded_vl', 5, 2)->default(0);
            $table->decimal('balance_forwarded_sl', 5, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('employees');
    }
};