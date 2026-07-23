<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        $permissionGroups = [
            'profiles' => [
                'profiles.view-private', 'profiles.create', 'profiles.edit', 'profiles.activate',
                'profiles.deactivate', 'profiles.ban', 'profiles.renew', 'profiles.transfer',
            ],
            'packages' => ['packages.manage', 'packages.assign'],
            'media' => ['media.upload', 'media.review', 'media.remove'],
            'moderation' => ['moderation.view', 'moderation.manage', 'moderation.appeals'],
            'verification' => ['verification.view', 'verification.manage'],
            'seo' => [
                'seo.content', 'seo.metadata', 'seo.redirects', 'seo.slugs',
                'seo.locations', 'seo.publish-locations',
            ],
            'administration' => ['roles.manage', 'settings.manage', 'policies.manage', 'audit.view', 'system.health'],
        ];

        $permissions = collect($permissionGroups)->flatMap(function (array $slugs, string $group) {
            return collect($slugs)->mapWithKeys(function (string $slug) use ($group) {
                $permission = Permission::query()->updateOrCreate(
                    ['slug' => $slug],
                    ['name' => str($slug)->after('.')->replace('-', ' ')->title(), 'group' => $group],
                );

                return [$slug => $permission];
            });
        });

        $roles = collect([
            'admin' => 'Admin',
            'csr' => 'CSR',
            'seo' => 'SEO',
            'subscriber' => 'Subscriber',
        ])->mapWithKeys(fn (string $name, string $slug) => [
            $slug => Role::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'is_system' => true],
            ),
        ]);

        $roles['admin']->permissions()->sync($permissions->pluck('id')->all());
        $roles['csr']->permissions()->sync($permissions->only([
            'profiles.view-private', 'profiles.create', 'profiles.edit', 'profiles.activate',
            'profiles.deactivate', 'profiles.ban', 'profiles.renew', 'profiles.transfer',
            'packages.assign', 'media.upload', 'media.review', 'media.remove', 'audit.view',
            'moderation.view', 'moderation.manage', 'moderation.appeals',
            'verification.view', 'verification.manage',
        ])->pluck('id')->all());
        $roles['seo']->permissions()->sync($permissions->only([
            'media.upload', 'media.remove', 'seo.content', 'seo.metadata', 'seo.redirects',
            'seo.slugs', 'seo.locations', 'seo.publish-locations', 'policies.manage',
        ])->pluck('id')->all());
        $roles['subscriber']->permissions()->sync([]);
    }
}
