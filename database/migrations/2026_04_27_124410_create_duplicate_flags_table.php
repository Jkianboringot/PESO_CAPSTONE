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
        Schema::create('duplicate_flags', function (Blueprint $table) {
            // The newer record that triggered the detection
            $table->foreignId('applicant_id_new')
                  ->constrained('applicants')
                  ->restrictOnDelete();
            // The existing record that matched
            $table->foreignId('applicant_id_existing')
                  ->constrained('applicants')
                  ->restrictOnDelete();
            // Criteria matched (2 or 3 of the composite algorithm)
            $table->boolean('matched_phonetic')->default(false);
            $table->boolean('matched_birthdate')->default(false);
            $table->boolean('matched_contact')->default(false);
            $table->unsignedTinyInteger('match_score'); // 2 or 3
            // Resolution fields
            $table->enum('resolution_status',
                  ['Pending','Merged','Retained Both','Deleted'])
                  ->default('Pending');
            $table->foreignId('resolved_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duplicate_flags');
    }
};
