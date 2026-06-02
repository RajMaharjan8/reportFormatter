<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Seed the default super-admin account. Idempotent: re-running keeps the
     * single admin@admin.com row in place. The plain password is hashed once
     * by the User model's "hashed" cast — do not pre-hash here.
     */
    public function run(): void
    {
        User::firstOrNew(['email' => 'admin@admin.com'])
            ->forceFill([
                'name' => 'Super Admin',
                'is_admin' => true,
                'password' => 'password2026',
                'email_verified_at' => now(),
            ])
            ->save();
    }
}
