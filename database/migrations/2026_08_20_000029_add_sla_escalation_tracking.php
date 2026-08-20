<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->timestamp('sla_escalated_at')->nullable()->index();
        });
        Schema::table('moderation_appeals', function (Blueprint $table): void {
            $table->timestamp('sla_escalated_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->dropColumn('sla_escalated_at');
        });
        Schema::table('moderation_appeals', function (Blueprint $table): void {
            $table->dropColumn('sla_escalated_at');
        });
    }
};
