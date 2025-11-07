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
        Schema::create('bounces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('send_log_id')->nullable()->constrained()->onDelete('set null');
            $table->string('message_id')->index();
            $table->string('recipient_email')->index();
            $table->enum('bounce_type', [
                'hard',
                'soft',
                'transient',
                'permanent',
                'unknown'
            ])->default('unknown');
            $table->enum('bounce_category', [
                'invalid_address',
                'mailbox_full',
                'spam_related',
                'dns_error',
                'connection_error',
                'policy_related',
                'content_rejected',
                'other'
            ])->default('other');
            $table->integer('smtp_code')->nullable();
            $table->text('smtp_response')->nullable();
            $table->text('diagnostic_code')->nullable();
            $table->text('raw_message')->nullable();
            $table->boolean('is_suppressed')->default(false);
            $table->timestamp('suppressed_until')->nullable();
            $table->timestamps();

            $table->index(['recipient_email', 'bounce_type']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bounces');
    }
};
