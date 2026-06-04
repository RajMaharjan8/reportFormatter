<?php

namespace App\Support\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;

/**
 * Khalti ePayment (KPG-2) integration: initiates a payment to get a redirect
 * URL and verifies the transaction via the lookup endpoint.
 *
 * @see https://docs.khalti.com/khalti-epayment/
 */
class KhaltiGateway
{
    /**
     * Start a payment and return the hosted payment URL to redirect the user to.
     * Stores the returned `pidx` on the payment for later verification.
     */
    public function initiate(Payment $payment, string $returnUrl, string $websiteUrl, string $orderName): ?string
    {
        $config = PaymentSettings::khalti();

        $response = Http::withHeaders([
            'Authorization' => 'Key '.$config['secret_key'],
        ])->acceptJson()->post($config['base_url'].'epayment/initiate/', [
            'return_url' => $returnUrl,
            'website_url' => $websiteUrl,
            'amount' => (int) round((float) $payment->amount * 100), // paisa
            'purchase_order_id' => $payment->transaction_uuid,
            'purchase_order_name' => $orderName,
        ]);

        if (! $response->ok() || ! $response->json('payment_url')) {
            return null;
        }

        $payment->update(['pidx' => $response->json('pidx')]);

        return $response->json('payment_url');
    }

    /**
     * Verify the payment via the lookup endpoint; only a "Completed" status is
     * treated as paid.
     */
    public function verify(Payment $payment): bool
    {
        $config = PaymentSettings::khalti();

        $response = Http::withHeaders([
            'Authorization' => 'Key '.$config['secret_key'],
        ])->acceptJson()->post($config['base_url'].'epayment/lookup/', [
            'pidx' => $payment->pidx,
        ]);

        if (! $response->ok() || $response->json('status') !== 'Completed') {
            return false;
        }

        $payment->update([
            'ref_id' => $response->json('transaction_id'),
            'meta' => $response->json(),
        ]);

        return true;
    }
}
