<?php

use App\Http\Controllers\PaymentController;
use App\Models\Payment;
use App\Models\Report;
use App\Models\Setting;
use App\Support\Payments\EsewaGateway;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

const ESEWA_SECRET = '8gBm/:&EnhH.1/q(';

function configureEsewa(): void
{
    Setting::set('esewa_enabled', '1');
    Setting::set('esewa_mode', 'test');
    Setting::set('esewa_product_code', 'EPAYTEST');
    Setting::set('esewa_secret_key', Crypt::encryptString(ESEWA_SECRET));
    Setting::set('download_price', '50');
}

it('creates a pending payment and renders a correctly signed eSewa form', function () {
    $user = loginAsTestUser();
    $report = Report::factory()->create(['user_id' => $user->id]);
    configureEsewa();

    $response = $this->post(route('reports.pay', ['report' => $report, 'gateway' => 'esewa']))->assertOk();

    $payment = Payment::where('report_id', $report->id)->firstOrFail();
    expect($payment->status)->toBe(Payment::STATUS_PENDING)
        ->and((float) $payment->amount)->toBe(50.0)
        ->and($payment->gateway)->toBe('esewa');

    $expectedSignature = (new EsewaGateway)->sign('50.00', $payment->transaction_uuid, 'EPAYTEST', ESEWA_SECRET);

    $response->assertSee('rc-epay.esewa.com.np', false)
        ->assertSee($expectedSignature, false);
});

it('completes the payment and arms the unlock on a verified eSewa callback', function () {
    Http::fake([
        'rc.esewa.com.np/*' => Http::response(['status' => 'COMPLETE', 'ref_id' => 'REF123']),
    ]);

    $user = loginAsTestUser();
    $report = Report::factory()->create(['user_id' => $user->id]);
    configureEsewa();

    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'report_id' => $report->id,
        'gateway' => 'esewa',
        'amount' => 50,
    ]);

    $data = base64_encode(json_encode([
        'status' => 'COMPLETE',
        'transaction_uuid' => $payment->transaction_uuid,
        'transaction_code' => 'TXN999',
    ]));

    $this->get(route('reports.pay.esewa.callback', ['report' => $report, 'data' => $data]))
        ->assertRedirect(route('reports.output', $report))
        ->assertSessionHas(PaymentController::unlockKey($report), $payment->id);

    expect($payment->fresh()->status)->toBe(Payment::STATUS_COMPLETED)
        ->and($payment->fresh()->ref_id)->toBe('REF123');
});

it('marks the payment failed when the eSewa status is not COMPLETE', function () {
    Http::fake([
        'rc.esewa.com.np/*' => Http::response(['status' => 'PENDING']),
    ]);

    $user = loginAsTestUser();
    $report = Report::factory()->create(['user_id' => $user->id]);
    configureEsewa();

    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'report_id' => $report->id,
        'gateway' => 'esewa',
        'amount' => 50,
    ]);

    $data = base64_encode(json_encode(['status' => 'COMPLETE', 'transaction_uuid' => $payment->transaction_uuid]));

    $this->get(route('reports.pay.esewa.callback', ['report' => $report, 'data' => $data]))
        ->assertRedirect(route('reports.output', $report))
        ->assertSessionMissing(PaymentController::unlockKey($report));

    expect($payment->fresh()->status)->toBe(Payment::STATUS_FAILED);
});
