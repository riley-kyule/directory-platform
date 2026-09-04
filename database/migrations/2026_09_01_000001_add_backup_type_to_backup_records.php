<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_records', function (Blueprint $table): void {
            $table->string('backup_type', 24)->default('database')->after('id')->index();
        });

        DB::table('backup_records')->where('path', 'like', '%/media-%')->update(['backup_type' => 'media']);
    }

    public function down(): void
    {
        Schema::table('backup_records', function (Blueprint $table): void {
            $table->dropIndex(['backup_type']);
            $table->dropColumn('backup_type');
        });
    }
};
