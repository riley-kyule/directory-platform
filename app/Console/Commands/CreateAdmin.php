<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Nothing seeds an actual admin user — AccessControlSeeder only creates the
 * Admin role — so there was previously no supported way to get the first
 * privileged account other than a manual DB edit. This is that command.
 */
class CreateAdmin extends Command
{
    protected $signature = 'admin:create {--email=} {--name=} {--password=}';

    protected $description = 'Create a user and attach the Admin role — for bootstrapping the very first privileged account';

    public function handle(): int
    {
        $adminRole = Role::query()->where('slug', 'admin')->first();
        if (! $adminRole) {
            $this->error('The Admin role does not exist yet — run `php artisan db:seed --class=AccessControlSeeder` first.');

            return self::FAILURE;
        }

        $email = $this->option('email') ?: text('Email', validate: fn (string $value) => $this->fieldError('email', $value, ['required', 'email', 'unique:users,email']));
        $name = $this->option('name') ?: text('Name', validate: fn (string $value) => $this->fieldError('name', $value, ['required', 'string', 'max:255']));
        $plainPassword = $this->option('password') ?: password('Password', validate: fn (string $value) => $this->fieldError('password', $value, ['required', Password::defaults()]));

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $plainPassword],
            [
                'email' => ['required', 'email', 'unique:users,email'],
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', Password::defaults()],
            ],
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($plainPassword),
            'status' => 'active',
            'account_type' => AccountType::Member,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->roles()->attach($adminRole);

        $this->info("Admin account created: {$email}");

        return self::SUCCESS;
    }

    /** @param  array<int, mixed>  $rules */
    private function fieldError(string $field, string $value, array $rules): ?string
    {
        $validator = Validator::make([$field => $value], [$field => $rules]);

        return $validator->fails() ? $validator->errors()->first() : null;
    }
}
