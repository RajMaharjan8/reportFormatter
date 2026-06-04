<?php

use App\Http\Controllers\PaymentController;
use App\Models\Payment;
use App\Models\Report;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

function configureKhalti(): void
{
    Setting::set('khalti_enabled', '1');
    Setting::set('khalti_mode', 'test');
    Setting::set('khalti_public_key', 'test-public-key');
    Setting::set('khalti_secret_key', Crypt::encryptString('test-secret-key'));
    Setting::set('download_price', '50');
}

it('initiates a Khalti payment and redirects to the hosted payment url', function () {
    Http::fake([
        'dev.khalti.com/api/v2/epayment/initiate/' => Http::response([
            'pidx' => 'PIDX123',
            'payment_url' => 'https://test-pay.khalti.com/?pidx=PIDX123',
        ]),
    ]);

    $user = loginAsTestUser();
    $report = Report::factory()->create(['user_id' => $user->id]);
    configureKhalti();

    $this->post(route('reports.pay', ['report' => $report, 'gateway' => 'khalti']))
        ->assertRedirect('https://test-pay.khalti.com/?pidx=PIDX123');

    $payment = Payment::where('report_id', $report->id)->firstOrFail();
    expect($payment->gateway)->toBe('khalti')
        ->and($payment->status)->toBe(Payment::STATUS_PENDING)
        ->and($payment->pidx)->toBe('PIDX123');
});

it('completes the payment and arms the unlock on a verified Khalti callback', function () {
    Http::fake([
        'dev.khalti.com/api/v2/epayment/lookup/' => Http::response([
            'status' => 'Completed',
            'transaction_id' => 'TXN-KH-1',
        ]),
    ]);

    $user = loginAsTestUser();
    $report = Report::factory()->create(['user_id' => $user->id]);
    configureKhalti();

    $payment = Payment::factory()->khalti()->create([
        'user_id' => $user->id,
        'report_id' => $report->id,
        'pidx' => 'PIDX123',
        'amount' => 50,
    ]);

    $this->get(route('reports.pay.khalti.callback', ['report' => $report, 'pidx' => 'PIDX123']))
        ->assertRedirect(route('reports.output', $report))
        ->assertSessionHas(PaymentController::unlockKey($report), $payment->id);

    expect($payment->fresh()->status)->toBe(Payment::STATUS_COMPLETED)
        ->and($payment->fresh()->ref_id)->toBe('TXN-KH-1');
});

it('does not unlock when the Khalti status is not Completed', function () {
    Http::fake([
        'dev.khalti.com/api/v2/epayment/lookup/' => Http::response([
            'status' => 'Pending',
        ]),
    ]);

    $user = loginAsTestUser();
    $report = Report::factory()->create(['user_id' => $user->id]);
    configureKhalti();

    $payment = Payment::factory()->khalti()->create([
        'user_id' => $user->id,
        'report_id' => $report->id,
        'pidx' => 'PIDX123',
        'amount' => 50,
    ]);

    $this->get(route('reports.pay.khalti.callback', ['report' => $report, 'pidx' => 'PIDX123']))
        ->assertRedirect(route('reports.output', $report))
        ->assertSessionMissing(PaymentController::unlockKey($report));

    expect($payment->fresh()->status)->toBe(Payment::STATUS_FAILED);
});
