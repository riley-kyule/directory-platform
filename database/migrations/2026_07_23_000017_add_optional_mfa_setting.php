<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('directory_settings')->updateOrInsert(
            ['key' => 'security.privileged_mfa_enforced'],
            [
                'value' => '0',
                'value_type' => 'boolean',
                'group' => 'security',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('directory_settings')->where('key', 'security.privileged_mfa_enforced')->delete();
    }
};
