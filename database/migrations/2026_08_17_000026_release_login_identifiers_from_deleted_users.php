<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('deleted_at')
            ->whereNull('anonymized_at')
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    DB::table('users')->where('id', $user->id)->update([
                        'email' => 'deleted-'.Str::uuid().'@deleted.invalid',
                        'google_subject' => null,
                        'google_sso_linked_at' => null,
                        'google_sso_last_login_at' => null,
                        'remember_token' => null,
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Login identifiers cannot be restored without retaining the deleted PII.
    }
};
