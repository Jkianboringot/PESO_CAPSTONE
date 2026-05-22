<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('applicant_skill', function (Blueprint $table) {
            $table->foreignId('applicant_id')
                ->constrained('applicants')
                ->cascadeOnDelete();
            $table->foreignId('skill_id')
                ->constrained('skills')
                ->restrictOnDelete();
            $table->enum(
                'proficiency_level',
                ['Beginner', 'Intermediate', 'Advanced', 'Expert']
            )
                ->default('Beginner');
            $table->primary(['applicant_id', 'skill_id']); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applicant_skill');
    }
};
