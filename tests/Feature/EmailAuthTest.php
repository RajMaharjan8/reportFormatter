<?php

use App\Mail\OtpCode;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/** Pull the plain code out of the OTP email that was just sent. */
function capturedOtp(): string
{
    $code = '';
    Mail::assertSent(OtpCode::class, function (OtpCode $mail) use (&$code) {
        $code = $mail->code;

        return true;
    });

    return $code;
}

it('registers a user as unverified and emails an OTP', function () {
    Mail::fake();

    Livewire::test('pages::auth.register')
        ->set('name', 'Reet Maharjan')
        ->set('email', 'reet@example.com')
        ->set('password', 'secret123')
        ->set('password_confirmation', 'secret123')
        ->call('register')
        ->assertRedirect(route('verify-otp'));

    $user = User::where('email', 'reet@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->email_verified_at)->toBeNull();

    Mail::assertSent(OtpCode::class);
    expect(session('otp_email'))->toBe('reet@example.com');
});

it('verifies the OTP, logs the user in, and marks them verified', function () {
    Mail::fake();

    Livewire::test('pages::auth.register')
        ->set('name', 'Reet')
        ->set('email', 'reet@example.com')
        ->set('password', 'secret123')
        ->set('password_confirmation', 'secret123')
        ->call('register');

    Livewire::test('pages::auth.verify-otp')
        ->set('code', capturedOtp())
        ->call('verify')
        ->assertHasNoErrors()
        ->assertRedirect(route('reports.index'));

    expect(Auth::check())->toBeTrue()
        ->and(User::where('email', 'reet@example.com')->first()->email_verified_at)->not->toBeNull();
});

it('rejects an incorrect OTP and keeps the user unverified', function () {
    Mail::fake();

    Livewire::test('pages::auth.register')
        ->set('name', 'Reet')
        ->set('email', 'reet@example.com')
        ->set('password', 'secret123')
        ->set('password_confirmation', 'secret123')
        ->call('register');

    $wrong = capturedOtp() === '111111' ? '222222' : '111111';

    Livewire::test('pages::auth.verify-otp')
        ->set('code', $wrong)
        ->call('verify')
        ->assertHasErrors('code');

    expect(Auth::check())->toBeFalse()
        ->and(User::where('email', 'reet@example.com')->first()->email_verified_at)->toBeNull();
});

it('logs in a verified user with email and password', function () {
    $user = User::factory()->create(['email' => 'me@example.com']); // factory password = "password", verified

    Livewire::test('pages::auth.login')
        ->set('email', 'me@example.com')
        ->set('password', 'password')
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect(route('reports.index'));

    expect(Auth::id())->toBe($user->id);
});

it('rejects a wrong password', function () {
    User::factory()->create(['email' => 'me@example.com']);

    Livewire::test('pages::auth.login')
        ->set('email', 'me@example.com')
        ->set('password', 'wrong-password')
        ->call('authenticate')
        ->assertHasErrors('email');

    expect(Auth::check())->toBeFalse();
});

it('routes an unverified user to OTP verification on login instead of signing in', function () {
    Mail::fake();
    User::factory()->create(['email' => 'pending@example.com', 'email_verified_at' => null]);

    Livewire::test('pages::auth.login')
        ->set('email', 'pending@example.com')
        ->set('password', 'password')
        ->call('authenticate')
        ->assertRedirect(route('verify-otp'));

    expect(Auth::check())->toBeFalse();
    Mail::assertSent(OtpCode::class);
});

it('resets a password through the OTP forgot-password flow', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => 'me@example.com']);

    Livewire::test('pages::auth.forgot-password')
        ->set('email', 'me@example.com')
        ->call('sendCode')
        ->assertSet('step', 2)
        ->set('code', capturedOtp())
        ->set('password', 'brand-new-pass')
        ->set('password_confirmation', 'brand-new-pass')
        ->call('resetPassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    expect(Hash::check('brand-new-pass', $user->fresh()->password))->toBeTrue();
});

it('does not send a reset code for an unknown email', function () {
    Mail::fake();

    Livewire::test('pages::auth.forgot-password')
        ->set('email', 'nobody@example.com')
        ->call('sendCode')
        ->assertHasErrors('email');

    Mail::assertNothingSent();
});

it('rejects an expired OTP', function () {
    Mail::fake();
    $code = Otp::send('me@example.com', Otp::PURPOSE_REGISTRATION);

    Otp::where('email', 'me@example.com')->update(['expires_at' => now()->subMinute()]);

    expect(Otp::consume('me@example.com', Otp::PURPOSE_REGISTRATION, $code))->toBeFalse();
});
