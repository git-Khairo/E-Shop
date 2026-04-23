<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo accounts for local testing. Safe to re-run (idempotent).
 *
 *   admin@eshop.local    / password   (role: admin if Spatie roles are seeded)
 *   customer@eshop.local / password   (regular customer)
 */
class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@eshop.local'],
            [
                'username' => 'admin',
                'password' => 'password',
            ]
        );

        if (method_exists($admin, 'assignRole')) {
            try {
                $admin->assignRole('admin');
            } catch (\Throwable) {
                // roles table may not exist yet; safe to ignore
            }
        }

        User::firstOrCreate(
            ['email' => 'customer@eshop.local'],
            [
                'username' => 'customer',
                'password' => 'password',
            ]
        );

        for ($i = 1; $i <= 3; $i++) {
            User::firstOrCreate(
                ['email' => "user{$i}@eshop.local"],
                [
                    'username' => "user{$i}",
                    'password' => 'password',
                ]
            );
        }
    }
}
