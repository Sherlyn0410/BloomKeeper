<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $name = env('ADMIN_NAME');
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $name || ! $email || ! $password) {
            throw new \RuntimeException(
                'ADMIN_NAME, ADMIN_EMAIL, and ADMIN_PASSWORD must be configured before seeding the administrator account.',
            );
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'role' => 'admin',
                'password' => Hash::make($password),
            ],
        );

        User::where('role', 'admin')
            ->where('email', '!=', $email)
            ->update(['role' => 'florist']);
    }
}
