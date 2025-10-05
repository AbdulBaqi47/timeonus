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
        Schema::create('salary_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('attendance_day_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('time_credit');
            $table->integer('adjustment_seconds')->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status')->default('pending');
            $table->string('reason')->nullable();
            $table->string('notes', 1024)->nullable();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_adjustments');
    }
};
