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
        Schema::create('mailboxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->onDelete('cascade');
            $table->string('local_part'); // e.g., 'noreply' in noreply@domain.com
            $table->string('email')->unique(); // full email address
            $table->string('password'); // hashed password for SMTP auth
            $table->integer('quota_mb')->default(1024); // quota in MB
            $table->integer('used_mb')->default(0); // used space in MB
            $table->boolean('is_active')->default(true);
            $table->boolean('can_send')->default(true);
            $table->boolean('can_receive')->default(true);
            $table->integer('daily_send_limit')->default(1000);
            $table->integer('daily_send_count')->default(0);
            $table->date('daily_send_reset_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['domain_id', 'local_part']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mailboxes');
    }
};
