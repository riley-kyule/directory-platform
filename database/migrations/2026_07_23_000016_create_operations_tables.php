<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_heartbeats', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->timestamp('last_seen_at')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('backup_records', function (Blueprint $table) {
            $table->id();
            $table->string('disk');
            $table->string('path');
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum_sha256', 64);
            $table->string('status', 24)->default('completed')->index();
            $table->timestamp('completed_at')->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_records');
        Schema::dropIfExists('system_heartbeats');
    }
};
