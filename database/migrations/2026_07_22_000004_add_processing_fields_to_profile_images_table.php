<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profile_images', function (Blueprint $table) {
            $table->json('derivatives')->nullable()->after('perceptual_hash');
            $table->text('processing_error')->nullable()->after('derivatives');
        });
    }

    public function down(): void
    {
        Schema::table('profile_images', function (Blueprint $table) {
            $table->dropColumn(['derivatives', 'processing_error']);
        });
    }
};
