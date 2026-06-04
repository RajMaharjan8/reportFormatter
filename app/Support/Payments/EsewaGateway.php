<?php

namespace App\Support\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;

/**
 * eSewa ePay v2 integration: builds the signed redirect form and verifies the
 * transaction server-side against the status-check API.
 *
 * @see https://developer.esewa.com.np/pages/Epay
 */
class EsewaGateway
{
    /**
     * The fields signed into the HMAC, in the exact order eSewa expects.
     */
    private const SIGNED_FIELD_NAMES = 'total_amount,transaction_uuid,product_code';

    /**
     * Form parameters to POST to the eSewa payment page (all values are strings).
     *
     * @return array{amount: string, tax_amount: string, total_amount: string, transaction_uuid: string, product_code: string, product_service_charge: string, product_delivery_charge: string, success_url: string, failure_url: string, signed_field_names: string, signature: string}
     */
    public function formParams(Payment $payment, string $successUrl, string $failureUrl): array
    {
        $config = PaymentSettings::esewa();
        $totalAmount = number_format((float) $payment->amount, 2, '.', '');

        return [
            'amount' => $totalAmount,
            'tax_amount' => '0',
            'total_amount' => $totalAmount,
            'transaction_uuid' => $payment->transaction_uuid,
            'product_code' => $config['product_code'],
            'product_service_charge' => '0',
            'product_delivery_charge' => '0',
            'success_url' => $successUrl,
            'failure_url' => $failureUrl,
            'signed_field_names' => self::SIGNED_FIELD_NAMES,
            'signature' => $this->sign($totalAmount, $payment->transaction_uuid, $config['product_code'], $config['secret_key']),
        ];
    }

    /**
     * The eSewa form action URL for the configured mode.
     */
    public function formUrl(): string
    {
        return PaymentSettings::esewa()['form_url'];
    }

    /**
     * base64( HMAC-SHA256( "total_amount=..,transaction_uuid=..,product_code=..", secret ) ).
     */
    public function sign(string $totalAmount, string $transactionUuid, string $productCode, string $secretKey): string
    {
        $message = "total_amount={$totalAmount},transaction_uuid={$transactionUuid},product_code={$productCode}";

        return base64_encode(hash_hmac('sha256', $message, $secretKey, true));
    }

    /**
     * Verify a returned transaction. eSewa appends a base64-encoded JSON `data`
     * blob to the success URL; we confirm it reads COMPLETE and then re-check
     * server-to-server against the status API before trusting it.
     */
    public function verify(Payment $payment, ?string $base64Data): bool
    {
        $config = PaymentSettings::esewa();
        $decoded = $base64Data ? json_decode((string) base64_decode($base64Data, true), true) : null;

        if (! is_array($decoded) || ($decoded['status'] ?? null) !== 'COMPLETE') {
            return false;
        }

        $totalAmount = number_format((float) $payment->amount, 2, '.', '');

        $response = Http::acceptJson()->get($config['status_url'], [
            'product_code' => $config['product_code'],
            'total_amount' => $totalAmount,
            'transaction_uuid' => $payment->transaction_uuid,
        ]);

        if (! $response->ok() || $response->json('status') !== 'COMPLETE') {
            return false;
        }

        $payment->update([
            'ref_id' => $response->json('ref_id') ?? ($decoded['transaction_code'] ?? null),
            'meta' => $response->json(),
        ]);

        return true;
    }
}
