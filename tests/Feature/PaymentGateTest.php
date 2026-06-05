<?php

use App\Http\Controllers\PaymentController;
use App\Models\Payment;
use App\Models\Report;
use App\Models\Setting;
use App\Models\User;

function enableEsewa(): void
{
    Setting::set('esewa_enabled', '1');
    Setting::set('esewa_product_code', 'EPAYTEST');
    Setting::set('download_price', '50');
}

it('shows the print button and no pay options when no gateway is enabled', function () {
    $user = loginAsTestUser();
    $report = Report::factory()->create(['user_id' => $user->id]);

    $this->get(route('reports.output', $report))
        ->assertOk()
        ->assertSee('Print / Save as PDF')
        ->assertDontSee('to download:');
});

it('shows the paywall over a free preview, with no print button, when unpaid', function () {
    $user = loginAsTestUser();
    $report = Report::factory()->create(['user_id' => $user->id]);
    enableEsewa();

    $this->get(route('reports.output', $report))
        ->assertOk()
        ->assertSee('Download your report')
        ->assertSee('eSewa')
        ->assertSee('report-source', false)   // preview is rendered (free to view)
        ->assertSee('paywall-print', false)   // print output is blocked
        ->assertDontSee('Print / Save as PDF');
});

it('shows both gateways on the paywall when both are enabled', function () {
    $user = loginAsTestUser();
    $report = Report::factory()->create(['user_id' => $user->id]);
    enableEsewa();
    Setting::set('khalti_enabled', '1');

    $this->get(route('reports.output', $report))
        ->assertOk()
        ->assertSee('eSewa')
        ->assertSee('Khalti');
});

it('still locks the download when a gateway is enabled but no price is set', function () {
    $user = loginAsTestUser();
    $report = Report::factory()->create(['user_id' => $user->id]);

    Setting::set('esewa_enabled', '1'); // price left unset (free would be wrong)

    $this->get(route('reports.output', $report))
        ->assertOk()
        ->assertDontSee('Print / Save as PDF')
        ->assertSee('no price has been set');
});

it('shows the print button once a redeemable payment unlocks the session', function () {
    $user = loginAsTestUser();
    $report = Report::factory()->create(['user_id' => $user->id]);
    enableEsewa();

    $payment = Payment::factory()->completed()->create([
        'user_id' => $user->id,
        'report_id' => $report->id,
    ]);

    $this->withSession([PaymentController::unlockKey($report) => $payment->id])
        ->get(route('reports.output', $report))
        ->assertOk()
        ->assertSee('Print / Save as PDF');
});

it('consumes the unlock so the next download requires payment again', function () {
    $user = loginAsTestUser();
    $report = Report::factory()->create(['user_id' => $user->id]);
    enableEsewa();

    $payment = Payment::factory()->completed()->create([
        'user_id' => $user->id,
        'report_id' => $report->id,
    ]);

    $this->withSession([PaymentController::unlockKey($report) => $payment->id])
        ->post(route('reports.download.consume', $report))
        ->assertNoContent();

    expect($payment->fresh()->consumed_at)->not->toBeNull();

    // Without an armed unlock the gate is closed again.
    $this->get(route('reports.output', $report))->assertDontSee('Print / Save as PDF');
});

it('forbids paying for or viewing another user’s report', function () {
    $owner = User::factory()->create();
    $report = Report::factory()->create(['user_id' => $owner->id]);
    enableEsewa();

    $this->actingAs(User::factory()->create());

    $this->get(route('reports.output', $report))->assertForbidden();
    $this->post(route('reports.pay', ['report' => $report, 'gateway' => 'esewa']))->assertForbidden();
});
