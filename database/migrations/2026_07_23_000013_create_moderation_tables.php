<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reporter_email')->nullable();
            $table->string('reporter_email_hash', 64)->nullable()->index();
            $table->string('category', 40)->index();
            $table->text('details');
            $table->string('priority', 20)->default('normal')->index();
            $table->string('status', 24)->default('new')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_fingerprint', 64)->nullable()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'priority', 'created_at']);
        });

        Schema::create('moderation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40)->index();
            $table->string('previous_profile_status', 24)->nullable();
            $table->string('new_profile_status', 24)->nullable();
            $table->text('reason');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['profile_id', 'created_at']);
        });

        Schema::create('moderation_appeals', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('moderation_action_id')->constrained()->restrictOnDelete();
            $table->foreignId('appellant_user_id')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->string('status', 24)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_appeals');
        Schema::dropIfExists('moderation_actions');
        Schema::dropIfExists('reports');
    }
};
