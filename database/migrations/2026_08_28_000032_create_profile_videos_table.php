<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('storage_directory');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 24)->default('quarantined')->index();
            $table->string('mime_type', 64);
            $table->unsignedBigInteger('file_size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('file_extension', 8)->default('mp4');
            $table->boolean('has_poster')->default(false);
            $table->string('exact_hash', 64)->index();
            $table->text('processing_error')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['profile_id', 'status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_videos');
    }
};
