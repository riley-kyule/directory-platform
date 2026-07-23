<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_checks', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->string('check_type', 40)->index();
            $table->string('status', 24)->index();
            $table->text('evidence_reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->index(['profile_id', 'check_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_checks');
    }
};
