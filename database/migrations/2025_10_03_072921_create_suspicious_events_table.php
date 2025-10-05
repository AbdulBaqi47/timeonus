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
        Schema::create('suspicious_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('activity_sample_id')->nullable()->constrained('activity_samples')->nullOnDelete();
            $table->timestamp('detected_at');
            $table->string('category');
            $table->string('severity')->default('medium');
            $table->decimal('confidence', 5, 2)->default(0);
            $table->string('summary', 1024);
            $table->string('status')->default('open');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution_notes', 1024)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suspicious_events');
    }
};
