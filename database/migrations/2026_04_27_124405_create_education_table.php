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
        Schema::create('education', function (Blueprint $table) {
                     $table->foreignId('applicant_id')
                  ->unique()  // Enforces 1-to-1 relationship
                  ->constrained('applicants')
                  ->cascadeOnDelete();
            $table->enum('highest_level', [
                'Elementary', 'High School', 'Senior High School',
                'Vocational/Technical', 'College Undergraduate',
                'College Graduate', 'Post-Graduate'
            ]);
            $table->string('course_program')->nullable();
            $table->string('school_name')->nullable();
            $table->year('year_graduated')->nullable();
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('education');
    }
};
