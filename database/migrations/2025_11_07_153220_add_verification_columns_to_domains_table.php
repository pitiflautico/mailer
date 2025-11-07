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
        Schema::table('domains', function (Blueprint $table) {
            // Add verification columns if they don't exist
            if (!Schema::hasColumn('domains', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('domains', 'dns_records')) {
                $table->json('dns_records')->nullable()->after('verified_at');
            }
            if (!Schema::hasColumn('domains', 'verification_results')) {
                $table->json('verification_results')->nullable()->after('dns_records');
            }
            if (!Schema::hasColumn('domains', 'last_verification_at')) {
                $table->timestamp('last_verification_at')->nullable()->after('verification_results');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            if (Schema::hasColumn('domains', 'last_verification_at')) {
                $table->dropColumn('last_verification_at');
            }
            if (Schema::hasColumn('domains', 'verification_results')) {
                $table->dropColumn('verification_results');
            }
            if (Schema::hasColumn('domains', 'dns_records')) {
                $table->dropColumn('dns_records');
            }
            if (Schema::hasColumn('domains', 'verified_at')) {
                $table->dropColumn('verified_at');
            }
        });
    }
};
