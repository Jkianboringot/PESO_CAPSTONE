<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('applicants', function (Blueprint $table) {
                    $table->id();
            $table->string('reference_id')->unique(); 
            $table->string('last_name');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->date('birthdate');
            $table->enum('sex', ['Male','Female','Prefer not to say']);
            $table->enum('civil_status', ['Single','Married','Widowed','Separated']);
            $table->string('contact_number', 20);
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->foreignId('barangay_id')
                  ->constrained('barangays')
                  ->restrictOnDelete();
            $table->enum('status', ['Pending','Verified','Flagged','Inactive'])
                  ->default('Pending');
            $table->boolean('is_active')->default(true); 
            $table->boolean('consent_given')->default(false); 
            $table->timestamp('consent_given_at')->nullable();
           
            $table->string('last_name_metaphone')->nullable()->index();
            $table->timestamps();
 
          
            $table->index(['birthdate']);
            $table->index(['sex']);
            $table->index(['civil_status']);
            $table->index(['status']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applicants');
    }
};
