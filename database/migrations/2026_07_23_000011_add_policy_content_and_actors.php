<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('policy_versions', function (Blueprint $table) {
            $table->string('title')->nullable()->after('version');
            $table->longText('content')->nullable()->after('title');
            $table->text('summary')->nullable()->after('content');
            $table->foreignId('created_by')->nullable()->after('requires_reacceptance')->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        Schema::table('policy_acceptances', function (Blueprint $table) {
            $table->index(['user_id', 'profile_id', 'action'], 'policy_acceptance_action_index');
        });
    }

    public function down(): void
    {
        Schema::table('policy_acceptances', function (Blueprint $table) {
            $table->dropIndex('policy_acceptance_action_index');
        });
        Schema::table('policy_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('published_by');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['title', 'content', 'summary']);
        });
    }
};
