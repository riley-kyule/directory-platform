<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('public_id')->unique()->after('id');
            $table->string('account_type', 24)->index()->after('password');
            $table->string('provider_type', 24)->nullable()->index()->after('account_type');
            $table->string('onboarding_status', 24)->default('registered')->index()->after('provider_type');
            $table->timestamp('onboarding_started_at')->nullable()->after('onboarding_status');
            $table->timestamp('onboarding_completed_at')->nullable()->after('onboarding_started_at');
            $table->timestamp('last_onboarding_activity_at')->nullable()->index()->after('onboarding_completed_at');
            $table->timestamp('last_seen_at')->nullable()->index()->after('last_onboarding_activity_at');
            $table->string('status', 24)->default('active')->index()->after('last_seen_at');
            $table->softDeletes();

            $table->index(['account_type', 'status']);
            $table->index(['provider_type', 'status']);
            $table->index(['onboarding_status', 'last_onboarding_activity_at'], 'users_onboarding_cleanup_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['account_type', 'status']);
            $table->dropIndex(['provider_type', 'status']);
            $table->dropIndex('users_onboarding_cleanup_index');
            $table->dropColumn([
                'public_id',
                'account_type',
                'provider_type',
                'onboarding_status',
                'onboarding_started_at',
                'onboarding_completed_at',
                'last_onboarding_activity_at',
                'last_seen_at',
                'status',
                'deleted_at',
            ]);
        });
    }
};
