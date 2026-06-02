<?php

use App\Models\Setting;
use App\Models\User;
use App\Providers\MailConfigServiceProvider;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('lets an admin sign in with email and password', function () {
    $admin = User::factory()->create(['is_admin' => true]); // factory password = "password"

    Livewire::test('pages::admin.login')
        ->set('email', $admin->email)
        ->set('password', 'password')
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.dashboard'));

    expect(auth()->id())->toBe($admin->id);
});

it('rejects a non-admin at the admin login', function () {
    $user = User::factory()->create(['is_admin' => false]);

    Livewire::test('pages::admin.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('authenticate')
        ->assertHasErrors('email');

    expect(auth()->check())->toBeFalse();
});

it('blocks non-admins from admin routes but allows admins', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();

    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
});

it('suspends and unsuspends a user from the admin users page', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create();

    $this->actingAs($admin);

    Livewire::test('pages::admin.users')->call('suspend', $target->id);
    expect($target->fresh()->isSuspended())->toBeTrue();

    Livewire::test('pages::admin.users')->call('unsuspend', $target->id);
    expect($target->fresh()->isSuspended())->toBeFalse();
});

it('never suspends an admin', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $other = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin);
    Livewire::test('pages::admin.users')->call('suspend', $other->id);

    expect($other->fresh()->isSuspended())->toBeFalse();
});

it('signs out and blocks a suspended user', function () {
    $user = User::factory()->create(['suspended_at' => now()]);

    $this->actingAs($user)
        ->get(route('reports.index'))
        ->assertRedirect(route('login'));

    expect(auth()->check())->toBeFalse();
});

it('records last_active_at for signed-in users', function () {
    $user = User::factory()->create(['last_active_at' => null]);

    $this->actingAs($user)->get(route('reports.index'))->assertOk();

    expect($user->fresh()->last_active_at)->not->toBeNull();
});

it('counts only recently active users as online', function () {
    User::factory()->create(['last_active_at' => now()]);
    User::factory()->create(['last_active_at' => now()->subMinutes(30)]);

    $online = User::where('last_active_at', '>=', now()->subMinutes(5))->count();

    expect($online)->toBe(1);
});

it('saves SMTP settings and applies them at runtime', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    Livewire::test('pages::admin.mail')
        ->set('mail_host', 'smtp.example.com')
        ->set('mail_port', 465)
        ->set('mail_encryption', 'ssl')
        ->set('mail_from_address', 'noreply@example.com')
        ->call('save')
        ->assertHasNoErrors();

    expect(Setting::get('mail_host'))->toBe('smtp.example.com');

    // The provider should override the runtime mail config from the settings.
    (new MailConfigServiceProvider(app()))->boot();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.example.com')
        ->and(config('mail.mailers.smtp.scheme'))->toBe('smtps');
});

it('lets an admin change their password', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    Livewire::test('pages::admin.password')
        ->set('current_password', 'password')
        ->set('password', 'newsecret123')
        ->set('password_confirmation', 'newsecret123')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('newsecret123', $admin->fresh()->password))->toBeTrue();
});

it('rejects a wrong current password', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    Livewire::test('pages::admin.password')
        ->set('current_password', 'wrong')
        ->set('password', 'newsecret123')
        ->set('password_confirmation', 'newsecret123')
        ->call('updatePassword')
        ->assertHasErrors('current_password');
});
