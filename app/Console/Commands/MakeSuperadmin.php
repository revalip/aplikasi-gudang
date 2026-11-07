<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Creates (or resets) a superadmin account from the command line, e.g. after wms:reset-data or on a fresh server.
 * If the email already exists it is promoted to superadmin and its password is replaced.
 */
class MakeSuperadmin extends Command
{
    protected $signature = 'wms:make-superadmin
        {email : Login email}
        {--name=Superadmin : Display name}
        {--password= : Password (asked securely if omitted, min 8 characters)}
        {--sql : Only print the equivalent SQL INSERT instead of touching the database}';

    protected $description = 'Create a superadmin account, or reset an existing one';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $password = (string) ($this->option('password') ?: $this->secret('Password (min 8 characters)'));

        $v = Validator::make(['email' => $email, 'password' => $password], [
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8',
        ]);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $e) {
                $this->error($e);
            }

            return self::FAILURE;
        }

        $hash = Hash::make($password);

        if ($this->option('sql')) {
            $q = fn (string $s) => "'" . str_replace("'", "''", $s) . "'";
            $this->line('INSERT INTO users (name, email, password, role, permissions, created_at, updated_at)');
            $this->line('VALUES (' . $q((string) $this->option('name')) . ', ' . $q($email) . ', ' . $q($hash) . ", 'superadmin', '[]', NOW(), NOW())");
            $this->line("ON DUPLICATE KEY UPDATE role = 'superadmin', permissions = '[]', password = VALUES(password), updated_at = NOW();");

            return self::SUCCESS;
        }

        $user = User::where('email', $email)->first();
        if ($user) {
            // the "password" attribute is cast to hashed, so assign the plain value
            $user->forceFill(['role' => 'superadmin', 'permissions' => [], 'password' => $password])->save();
            $this->info("Existing account {$email} is now a superadmin and its password was replaced.");
        } else {
            User::create(['name' => (string) $this->option('name'), 'email' => $email, 'password' => $password, 'role' => 'superadmin', 'permissions' => []]);
            $this->info("Superadmin {$email} created.");
        }
        Role::flush();

        return self::SUCCESS;
    }
}
