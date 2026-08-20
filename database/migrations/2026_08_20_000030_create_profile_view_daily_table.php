<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_view_daily', function (Blueprint $table): void {
            $table->id();
            $table->date('event_date');
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('view_count')->default(0);
            $table->timestamps();

            $table->unique(['event_date', 'profile_id'], 'profile_view_daily_unique');
            $table->index(['event_date', 'view_count']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_view_daily');
    }
};
