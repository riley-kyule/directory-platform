<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->foreignId('micro_location_id')
                ->nullable()
                ->after('sublocation_id')
                ->constrained('locations')
                ->nullOnDelete();
            $table->index(['micro_location_id', 'status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropForeign(['micro_location_id']);
            $table->dropIndex(['micro_location_id', 'status', 'expires_at']);
            $table->dropColumn('micro_location_id');
        });
    }
};
