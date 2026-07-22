<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('country_code', 2)->index();
            $table->string('type', 32)->index();
            $table->string('name');
            $table->string('slug');
            $table->string('full_slug')->unique();
            $table->string('status', 24)->default('draft')->index();
            $table->boolean('is_indexable')->default(false)->index();
            $table->unsignedInteger('published_profile_count')->default(0);
            $table->unsignedInteger('active_profile_count')->default(0);
            $table->timestamps();

            $table->unique(['parent_id', 'slug']);
            $table->index(['country_code', 'status', 'is_indexable']);
        });

        Schema::create('location_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('alias');
            $table->string('normalized_alias');
            $table->timestamps();
            $table->unique(['location_id', 'normalized_alias']);
        });

        Schema::create('location_contents', function (Blueprint $table) {
            $table->foreignId('location_id')->primary()->constrained()->cascadeOnDelete();
            $table->longText('intro_content');
            $table->json('faq_content')->nullable();
            $table->string('seo_title');
            $table->string('meta_description', 320);
            $table->string('canonical_path');
            $table->string('content_status', 24)->default('draft')->index();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('owner_user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->longText('description')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('taxonomy_options', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('type', 40);
            $table->string('slug');
            $table->string('label');
            $table->string('country_code', 2)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['type', 'slug', 'country_code']);
            $table->index(['type', 'is_active', 'sort_order']);
        });

        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('owner_user_id')->nullable()->unique()->constrained('users')->restrictOnDelete();
            $table->string('display_name');
            $table->string('slug')->unique();
            $table->string('headline')->nullable();
            $table->longText('description');
            $table->foreignId('primary_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('sublocation_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('gender_option_id')->constrained('taxonomy_options')->restrictOnDelete();
            $table->date('date_of_birth');
            $table->foreignId('ethnicity_option_id')->constrained('taxonomy_options')->restrictOnDelete();
            $table->foreignId('build_option_id')->constrained('taxonomy_options')->restrictOnDelete();
            $table->foreignId('bust_size_option_id')->nullable()->constrained('taxonomy_options')->restrictOnDelete();
            $table->boolean('allows_incall')->default(false);
            $table->boolean('allows_outcall')->default(false);
            $table->string('status', 24)->default('draft')->index();
            $table->string('verification_status', 24)->default('unverified')->index();
            $table->unsignedSmallInteger('quality_score')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('last_activated_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['primary_location_id', 'status', 'expires_at']);
            $table->index(['sublocation_id', 'status', 'expires_at']);
        });

        Schema::create('profile_slug_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->string('old_slug')->unique();
            $table->string('new_slug');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
        });

        Schema::create('agency_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();
            $table->timestamps();
            $table->index(['agency_id', 'unassigned_at']);
            $table->unique(['agency_id', 'profile_id', 'assigned_at']);
        });

        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedSmallInteger('image_limit');
            $table->unsignedSmallInteger('display_order');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('package_duration_options', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->unsignedSmallInteger('duration_days');
            $table->unsignedSmallInteger('display_order');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('profile_package_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_package_id')->constrained('packages')->restrictOnDelete();
            $table->string('status', 24)->default('pending')->index();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->text('decision_reason')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['profile_id', 'status']);
        });

        Schema::create('profile_package_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained()->restrictOnDelete();
            $table->foreignId('request_id')->nullable()->constrained('profile_package_requests')->nullOnDelete();
            $table->foreignId('previous_assignment_id')->nullable()->constrained('profile_package_assignments')->nullOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->index();
            $table->string('status', 24)->default('active')->index();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->string('assignment_source', 32)->default('manual');
            $table->text('reason');
            $table->timestamps();
            $table->index(['profile_id', 'status', 'expires_at']);
        });

        Schema::create('profile_contact_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('normalized_value');
            $table->string('display_value');
            $table->boolean('is_public')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['profile_id', 'type', 'normalized_value'], 'profile_contact_unique');
        });

        Schema::create('profile_details', function (Blueprint $table) {
            $table->foreignId('profile_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('hair_color_option_id')->nullable()->constrained('taxonomy_options')->nullOnDelete();
            $table->foreignId('hair_length_option_id')->nullable()->constrained('taxonomy_options')->nullOnDelete();
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->boolean('smoker')->nullable();
            $table->foreignId('sexual_orientation_option_id')->nullable()->constrained('taxonomy_options')->nullOnDelete();
            $table->string('website_url')->nullable();
            $table->string('instagram_handle')->nullable();
            $table->string('snapchat_handle')->nullable();
            $table->string('tiktok_handle')->nullable();
            $table->timestamps();
        });

        Schema::create('profile_services', function (Blueprint $table) {
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_option_id')->constrained('taxonomy_options')->restrictOnDelete();
            $table->primary(['profile_id', 'service_option_id']);
        });

        Schema::create('profile_languages', function (Blueprint $table) {
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('language_option_id')->constrained('taxonomy_options')->restrictOnDelete();
            $table->primary(['profile_id', 'language_option_id']);
        });

        Schema::create('profile_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->string('currency_code', 3);
            $table->foreignId('rate_period_option_id')->constrained('taxonomy_options')->restrictOnDelete();
            $table->decimal('price', 12, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['profile_id', 'currency_code', 'rate_period_option_id'], 'profile_rate_unique');
        });

        Schema::create('profile_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('storage_directory');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 24)->default('quarantined')->index();
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->decimal('aspect_ratio', 8, 4);
            $table->string('mime_type', 64);
            $table->unsignedBigInteger('file_size');
            $table->string('exact_hash', 64)->index();
            $table->string('perceptual_hash', 128)->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['profile_id', 'status', 'sort_order']);
        });

        Schema::create('policy_versions', function (Blueprint $table) {
            $table->id();
            $table->string('policy_type', 40)->index();
            $table->string('version', 40);
            $table->string('content_hash', 64);
            $table->timestamp('published_at')->nullable()->index();
            $table->boolean('requires_reacceptance')->default(false);
            $table->timestamps();
            $table->unique(['policy_type', 'version']);
        });

        Schema::create('policy_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('profile_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 64)->index();
            $table->timestamp('accepted_at');
            $table->json('request_context')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'policy_version_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80)->index();
            $table->string('target_type', 120);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('previous_state')->nullable();
            $table->json('new_state')->nullable();
            $table->uuid('request_id')->nullable()->index();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('policy_acceptances');
        Schema::dropIfExists('policy_versions');
        Schema::dropIfExists('profile_images');
        Schema::dropIfExists('profile_rates');
        Schema::dropIfExists('profile_languages');
        Schema::dropIfExists('profile_services');
        Schema::dropIfExists('profile_details');
        Schema::dropIfExists('profile_contact_methods');
        Schema::dropIfExists('profile_package_assignments');
        Schema::dropIfExists('profile_package_requests');
        Schema::dropIfExists('package_duration_options');
        Schema::dropIfExists('packages');
        Schema::dropIfExists('agency_profiles');
        Schema::dropIfExists('profile_slug_histories');
        Schema::dropIfExists('profiles');
        Schema::dropIfExists('taxonomy_options');
        Schema::dropIfExists('agencies');
        Schema::dropIfExists('location_contents');
        Schema::dropIfExists('location_aliases');
        Schema::dropIfExists('locations');
    }
};
