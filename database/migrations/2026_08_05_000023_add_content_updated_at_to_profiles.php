<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->timestamp('content_updated_at')->nullable()->after('updated_at');
        });

        // Backfill so existing rows have a sitemap lastmod immediately,
        // instead of every profile appearing "just changed" on first save.
        DB::table('profiles')->update(['content_updated_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('content_updated_at');
        });
    }
};
