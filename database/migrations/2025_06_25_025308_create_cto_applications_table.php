<?php
// database/migrations/xxxx_xx_xx_xxxxxx_create_cto_applications_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCtoApplicationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cto_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            
            // For CTO activities (credits earned)
            $table->string('special_order')->nullable();
            $table->date('date_of_activity_start')->nullable();
            $table->date('date_of_activity_end')->nullable();
            $table->text('activity')->nullable();
            $table->decimal('credits_earned', 8, 2)->nullable();
            
            // For CTO usage (credits deducted)
            $table->date('date_of_absence_start')->nullable();
            $table->date('date_of_absence_end')->nullable();
            $table->integer('no_of_days')->nullable();
            
            // Balance after this transaction
            $table->decimal('balance', 8, 2)->default(0);
            
            // Flag to distinguish between activity (credit) and usage (debit)
            $table->boolean('is_activity')->default(true);
            
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['employee_id', 'date_of_activity_start']);
            $table->index(['employee_id', 'date_of_absence_start']);
            $table->index(['employee_id', 'is_activity']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cto_applications');
    }
}