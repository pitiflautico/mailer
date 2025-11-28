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
        Schema::create('warmup_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mailbox_id')->constrained()->onDelete('cascade');
            $table->integer('day')->default(1); // Current day of warmup
            $table->integer('target_day')->default(30); // Total warmup days
            $table->integer('emails_sent_today')->default(0);
            $table->integer('emails_target_today')->default(5);
            $table->enum('status', ['active', 'paused', 'completed'])->default('active');
            $table->timestamp('last_send_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('stats')->nullable(); // Daily stats
            $table->timestamps();

            $table->index(['mailbox_id', 'status']);
            $table->index('last_send_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warmup_schedules');
    }
};
