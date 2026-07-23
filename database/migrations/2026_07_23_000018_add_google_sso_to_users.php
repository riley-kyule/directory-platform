<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_subject')->nullable()->unique()->after('email');
            $table->timestamp('google_sso_linked_at')->nullable()->after('google_subject');
            $table->timestamp('google_sso_last_login_at')->nullable()->after('google_sso_linked_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_subject']);
            $table->dropColumn(['google_subject', 'google_sso_linked_at', 'google_sso_last_login_at']);
        });
    }
};
