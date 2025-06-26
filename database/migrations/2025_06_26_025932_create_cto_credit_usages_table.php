<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCtoCreditUsagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cto_credit_usages', function (Blueprint $table) {
            $table->id();
            // The specific CTO activity record that provided credits
            $table->foreignId('cto_activity_id')
                  ->constrained('cto_applications')
                  ->onDelete('cascade'); // If the activity is deleted, this usage record is also deleted

            // The specific CTO absence record that consumed credits
            $table->foreignId('cto_absence_id')
                  ->constrained('cto_applications')
                  ->onDelete('cascade'); // If the absence is deleted, this usage record is also deleted

            $table->decimal('days_used', 8, 2); // How many days from the activity were used by this absence
            $table->timestamps();

            // Ensures that a specific activity credit is only linked to a specific absence once
            $table->unique(['cto_activity_id', 'cto_absence_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cto_credit_usages');
    }
}