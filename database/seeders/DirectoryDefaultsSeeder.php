<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DirectoryDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'vip', 'name' => 'VIP', 'image_limit' => 15, 'display_order' => 10],
            ['code' => 'premium', 'name' => 'Premium', 'image_limit' => 10, 'display_order' => 20],
            ['code' => 'basic', 'name' => 'Basic', 'image_limit' => 5, 'display_order' => 30],
        ] as $package) {
            Package::query()->updateOrCreate(['code' => $package['code']], $package + ['is_active' => true]);
        }

        foreach ([7, 14, 30, 60, 90, 180, 365] as $index => $days) {
            DB::table('package_duration_options')->updateOrInsert(
                ['duration_days' => $days],
                [
                    'label' => $days === 365 ? '1 year' : $days.' days',
                    'display_order' => ($index + 1) * 10,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }
}
