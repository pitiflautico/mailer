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
        Schema::create('send_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('mailbox_id')->nullable()->constrained()->onDelete('set null');
            $table->string('message_id')->unique()->nullable();
            $table->string('from_email')->index();
            $table->string('to_email')->index();
            $table->string('subject')->nullable();
            $table->text('body_preview')->nullable();
            $table->enum('status', [
                'queued',
                'sent',
                'delivered',
                'bounced',
                'failed',
                'rejected',
                'deferred'
            ])->default('queued')->index();
            $table->text('smtp_response')->nullable();
            $table->integer('smtp_code')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->json('headers')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'status']);
            $table->index('sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('send_logs');
    }
};
