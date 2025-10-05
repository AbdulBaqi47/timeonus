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
        Schema::create('attendance_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('office_location_id')->nullable()->constrained()->nullOnDelete();
            $table->date('work_date');
            $table->string('status')->default('present');
            $table->timestamp('login_at')->nullable();
            $table->timestamp('logout_at')->nullable();
            $table->timestamp('first_activity_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->unsignedInteger('total_work_seconds')->default(0);
            $table->unsignedInteger('total_idle_seconds')->default(0);
            $table->unsignedInteger('total_help_seconds')->default(0);
            $table->integer('manual_adjustment_seconds')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->string('late_reason')->nullable();
            $table->string('notes', 1024)->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->json('metrics_snapshot')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'work_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_days');
    }
};
