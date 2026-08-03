<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Only MySQL/MariaDB support FULLTEXT indexes; other drivers (SQLite in
     * local dev and tests) keep using the LIKE fallback in
     * PublicProfileListings::search().
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('profiles', function (Blueprint $table) {
            $table->fullText(['display_name', 'description']);
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropFullText(['display_name', 'description']);
        });
    }
};
