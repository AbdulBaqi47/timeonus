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
        Schema::create('salary_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('draft');
            $table->unsignedInteger('expected_work_seconds')->default(0);
            $table->unsignedInteger('actual_work_seconds')->default(0);
            $table->unsignedInteger('idle_seconds')->default(0);
            $table->unsignedInteger('help_seconds')->default(0);
            $table->integer('manual_adjustment_seconds')->default(0);
            $table->decimal('base_salary', 12, 2)->nullable();
            $table->decimal('gross_pay', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);
            $table->decimal('total_adjustments_amount', 12, 2)->default(0);
            $table->decimal('total_deductions_amount', 12, 2)->default(0);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->json('breakdown')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'period_start', 'period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_runs');
    }
};
