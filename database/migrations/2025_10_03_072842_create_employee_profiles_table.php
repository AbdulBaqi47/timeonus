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
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('employee_code')->nullable()->unique();
            $table->string('job_title')->nullable();
            $table->foreignId('primary_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->decimal('base_salary', 12, 2)->nullable();
            $table->decimal('hourly_rate', 8, 2)->nullable();
            $table->string('timezone')->default('UTC');
            $table->foreignId('default_office_location_id')->nullable()->constrained('office_locations')->nullOnDelete();
            $table->time('expected_start_time')->nullable();
            $table->time('expected_end_time')->nullable();
            $table->unsignedInteger('daily_idle_allowance_minutes')->default(0);
            $table->date('date_hired')->nullable();
            $table->date('date_left')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_profiles');
    }
};
