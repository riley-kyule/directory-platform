<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_contents', function (Blueprint $table) {
            $table->id();
            $table->string('page_key')->unique();
            $table->string('heading');
            $table->text('intro_content');
            $table->longText('bottom_content')->nullable();
            $table->string('seo_title');
            $table->string('meta_description', 320);
            $table->json('listing_sections')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('location_contents', function (Blueprint $table) {
            $table->string('heading')->nullable()->after('location_id');
            $table->longText('bottom_content')->nullable()->after('intro_content');
        });

        DB::table('page_contents')->insert([
            'page_key' => 'homepage',
            'heading' => 'Discover independent providers near you',
            'intro_content' => 'Browse active profiles by package and find the right connection for you.',
            'bottom_content' => null,
            'seo_title' => config('app.name').' — Find providers near you',
            'meta_description' => 'Browse active VIP, Premium, Basic and newly activated provider profiles.',
            'listing_sections' => json_encode([
                'vip' => ['heading' => 'VIP Escorts', 'description' => 'Featured profiles with our highest visibility package.'],
                'premium' => ['heading' => 'Premium Escorts', 'description' => 'Prominent profiles with enhanced directory visibility.'],
                'basic' => ['heading' => 'Basic Escorts', 'description' => 'All active profiles on the Basic package.'],
                'new' => ['heading' => 'New Escorts', 'description' => 'Recently activated provider profiles.'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('location_contents', function (Blueprint $table) {
            $table->dropColumn(['heading', 'bottom_content']);
        });

        Schema::dropIfExists('page_contents');
    }
};
