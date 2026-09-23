<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Creates the first admin on a production install. make:filament-user does not
 * fit this User model (it writes a `name` column and never sets is_admin), and
 * the seeders depend on Faker, which composer install --no-dev omits.
 */
class CreateAdminCommand extends Command
{
    protected $signature = 'cividash:create-admin
        {email : Email address of the admin}
        {--first-name=Admin : First name}
        {--last-name= : Last name}';

    protected $description = 'Create an admin user with access to all dashboards.';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        $validator = Validator::make(['email' => $email], [
            'email' => ['required', 'email', 'unique:users,email'],
        ]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first('email'));

            return self::FAILURE;
        }

        $password = (string) $this->secret('Password');
        if (strlen($password) < 12) {
            $this->error('The password must be at least 12 characters.');

            return self::FAILURE;
        }
        if ($password !== $this->secret('Confirm password')) {
            $this->error('The passwords do not match.');

            return self::FAILURE;
        }

        User::create([
            'first_name' => $this->option('first-name') ?: 'Admin',
            'last_name' => $this->option('last-name') ?: '',
            'email' => $email,
            'password' => $password,
            'is_active' => true,
            'is_admin' => true,
        ]);

        $this->info("Admin {$email} created.");

        return self::SUCCESS;
    }
}
