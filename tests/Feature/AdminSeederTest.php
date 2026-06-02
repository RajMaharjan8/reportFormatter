<?php

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Support\Facades\Auth;

it('seeds an admin that can authenticate, idempotently', function () {
    (new AdminSeeder)->run();
    (new AdminSeeder)->run(); // re-running must not duplicate

    expect(User::where('email', 'admin@admin.com')->count())->toBe(1);

    $admin = User::where('email', 'admin@admin.com')->first();

    expect($admin->isAdmin())->toBeTrue()
        ->and(Auth::attempt(['email' => 'admin@admin.com', 'password' => 'password2026']))->toBeTrue();
});
