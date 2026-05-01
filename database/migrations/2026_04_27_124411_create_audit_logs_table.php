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
        Schema::create('audit_logs', function (Blueprint $table) {
           $table->id();
            $table->foreignId('user_id')
                  ->nullable()  // null = guest/applicant action
                  ->constrained('users')
                  ->nullOnDelete();
            $table->string('action');         // e.g., "APPLICANT_CREATED"
            $table->string('model_type')->nullable(); // e.g., "Applicant"
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('changes')->nullable();   // Before/after JSON diff
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
 
            $table->index(['action']);
            $table->index(['model_type', 'model_id']);
            $table->index(['created_at']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
