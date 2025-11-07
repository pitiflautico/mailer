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
        Schema::create('suppression_list', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique()->index();
            $table->enum('reason', [
                'hard_bounce',
                'spam_complaint',
                'unsubscribe',
                'manual',
                'invalid_address',
                'policy_violation',
                'gdpr_request'
            ]);
            $table->text('notes')->nullable();
            $table->string('source')->nullable(); // bounces, complaints, api, manual
            $table->timestamp('suppressed_at')->index();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('domain_id')->nullable()->constrained()->onDelete('set null');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['suppressed_at', 'expires_at']);
        });

        Schema::create('unsubscribes', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->foreignId('domain_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('mailbox_id')->nullable()->constrained()->onDelete('set null');
            $table->string('list_type')->default('all'); // all, marketing, transactional
            $table->string('unsubscribe_token')->unique();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('unsubscribed_at');
            $table->timestamps();

            $table->unique(['email', 'domain_id', 'list_type']);
        });

        Schema::create('consent_records', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->foreignId('domain_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('consent_type', [
                'marketing',
                'transactional',
                'newsletter',
                'promotional',
                'data_processing'
            ]);
            $table->boolean('granted')->default(false);
            $table->enum('consent_method', [
                'opt_in',
                'double_opt_in',
                'implicit',
                'legitimate_interest'
            ]);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('consent_text')->nullable(); // Text shown to user
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('verification_token')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['email', 'consent_type', 'granted']);
        });

        Schema::create('spam_complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('send_log_id')->nullable()->constrained()->onDelete('set null');
            $table->string('complainant_email')->index();
            $table->string('message_id')->nullable();
            $table->enum('complaint_type', [
                'spam',
                'abuse',
                'fraud',
                'virus',
                'not_spam',
                'other'
            ])->default('spam');
            $table->text('complaint_details')->nullable();
            $table->string('feedback_type')->nullable(); // ARF feedback type
            $table->string('reported_by')->nullable(); // ESP, ISP, user
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('original_headers')->nullable();
            $table->boolean('auto_processed')->default(false);
            $table->boolean('suppressed')->default(false);
            $table->timestamps();
        });

        Schema::create('compliance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('action_type')->index(); // send, suppress, consent, gdpr_export, etc.
            $table->string('entity_type')->nullable(); // SendLog, Domain, etc.
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('email')->nullable()->index();
            $table->text('description');
            $table->enum('compliance_standard', [
                'gdpr',
                'can_spam',
                'casl',
                'pecr',
                'ccpa',
                'internal'
            ])->nullable();
            $table->boolean('compliant')->default(true);
            $table->text('non_compliance_reason')->nullable();
            $table->json('data_snapshot')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['action_type', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('ip_reputation', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->unique();
            $table->integer('reputation_score')->default(100); // 0-100
            $table->integer('spam_reports')->default(0);
            $table->integer('successful_sends')->default(0);
            $table->integer('failed_sends')->default(0);
            $table->integer('bounce_rate')->default(0); // percentage
            $table->boolean('is_blacklisted')->default(false);
            $table->json('blacklist_sources')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('blacklisted_at')->nullable();
            $table->timestamps();

            $table->index(['reputation_score', 'is_blacklisted']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ip_reputation');
        Schema::dropIfExists('compliance_logs');
        Schema::dropIfExists('spam_complaints');
        Schema::dropIfExists('consent_records');
        Schema::dropIfExists('unsubscribes');
        Schema::dropIfExists('suppression_list');
    }
};
