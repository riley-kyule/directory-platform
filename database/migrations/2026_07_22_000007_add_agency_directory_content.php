<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('page_contents')->insert([
            'page_key' => 'agencies',
            'heading' => 'Escort Agencies',
            'intro_content' => 'Browse agencies with currently active provider profiles.',
            'bottom_content' => null,
            'seo_title' => 'Escort Agencies — '.config('app.name'),
            'meta_description' => 'Browse public agencies and their currently active provider profiles.',
            'listing_sections' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('page_contents')->where('page_key', 'agencies')->delete();
    }
};
