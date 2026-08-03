<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_term_logs', function (Blueprint $table) {
            $table->id();
            $table->date('search_date')->index();
            $table->string('term');
            $table->unsignedInteger('search_count');
            $table->timestamps();

            $table->unique(['search_date', 'term']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_term_logs');
    }
};
