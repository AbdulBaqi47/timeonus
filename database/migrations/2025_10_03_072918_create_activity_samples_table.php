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
        Schema::create('activity_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_session_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->unsignedInteger('keyboard_events')->default(0);
            $table->unsignedInteger('mouse_events')->default(0);
            $table->unsignedInteger('touch_events')->default(0);
            $table->string('active_window')->nullable();
            $table->string('active_process')->nullable();
            $table->json('payload')->nullable();
            $table->boolean('is_suspected')->default(false);
            $table->string('source')->default('desktop_agent');
            $table->timestamps();

            $table->index(['user_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_samples');
    }
};
