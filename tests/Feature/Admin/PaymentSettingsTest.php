<?php

use App\Models\Setting;
use App\Models\User;
use App\Support\Payments\PaymentSettings;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;

it('renders the payments settings page for an admin', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->get(route('admin.payments'))->assertOk()->assertSee('eSewa Payment');
});

it('saves non-secret eSewa fields and the enabled flag', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    Livewire::test('pages::admin.payments')
        ->set('esewa_enabled', true)
        ->set('esewa_mode', 'test')
        ->set('esewa_merchant_id', 'MID123')
        ->set('esewa_product_code', 'EPAYTEST')
        ->call('saveEsewa')
        ->assertHasNoErrors();

    expect(Setting::get('esewa_enabled'))->toBe('1')
        ->and(Setting::get('esewa_product_code'))->toBe('EPAYTEST')
        ->and(PaymentSettings::isEsewaEnabled())->toBeTrue();
});

it('encrypts a secret key and a blank value keeps the existing one', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    Livewire::test('pages::admin.payments')
        ->set('esewa_product_code', 'EPAYTEST')
        ->set('esewa_secret_key', 'super-secret')
        ->call('saveEsewa')
        ->assertHasNoErrors();

    $stored = Setting::get('esewa_secret_key');
    expect($stored)->not->toBe('super-secret') // stored encrypted, not plaintext
        ->and(Crypt::decryptString($stored))->toBe('super-secret')
        ->and(PaymentSettings::secret('esewa_secret_key'))->toBe('super-secret');

    // Saving again with a blank secret keeps the previous value.
    Livewire::test('pages::admin.payments')
        ->set('esewa_product_code', 'EPAYTEST')
        ->set('esewa_secret_key', '')
        ->call('saveEsewa')
        ->assertHasNoErrors();

    expect(PaymentSettings::secret('esewa_secret_key'))->toBe('super-secret');
});

it('requires the product code when eSewa is enabled', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    Livewire::test('pages::admin.payments')
        ->set('esewa_enabled', true)
        ->set('esewa_product_code', '')
        ->call('saveEsewa')
        ->assertHasErrors('esewa_product_code');
});

it('validates the download price', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    Livewire::test('pages::admin.payments')
        ->set('download_price', 'abc')
        ->call('savePricing')
        ->assertHasErrors('download_price');

    Livewire::test('pages::admin.payments')
        ->set('download_price', '49.50')
        ->call('savePricing')
        ->assertHasNoErrors();

    expect(PaymentSettings::price())->toBe(49.5);
});
