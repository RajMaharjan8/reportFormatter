<?php

namespace App\Support\Payments;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Read-side helper over the key-value {@see Setting} store for payment config.
 *
 * Secret values (signing keys, API secrets) are stored encrypted; the typed
 * getters here decrypt them transparently. Gateway URLs resolve from each
 * gateway's test/live mode.
 */
class PaymentSettings
{
    /**
     * Settings keys holding encrypted secret values.
     *
     * @var list<string>
     */
    public const SECRET_KEYS = [
        'esewa_secret_key',
        'esewa_merchant_secret',
        'khalti_secret_key',
    ];

    public static function isEsewaEnabled(): bool
    {
        return Setting::get('esewa_enabled') === '1';
    }

    public static function isKhaltiEnabled(): bool
    {
        return Setting::get('khalti_enabled') === '1';
    }

    public static function anyEnabled(): bool
    {
        return static::isEsewaEnabled() || static::isKhaltiEnabled();
    }

    /**
     * Slugs of the enabled gateways, in display order.
     *
     * @return list<string>
     */
    public static function enabledGateways(): array
    {
        $gateways = [];

        if (static::isEsewaEnabled()) {
            $gateways[] = 'esewa';
        }

        if (static::isKhaltiEnabled()) {
            $gateways[] = 'khalti';
        }

        return $gateways;
    }

    public static function price(): float
    {
        return (float) Setting::get('download_price', '0');
    }

    /**
     * Decrypt a stored secret, tolerating values that were never encrypted.
     */
    public static function secret(string $key): string
    {
        $value = (string) Setting::get($key, '');

        if ($value === '') {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return $value;
        }
    }

    /**
     * eSewa configuration with mode-resolved endpoints.
     *
     * @return array{mode: string, merchant_id: string, product_code: string, secret_key: string, merchant_secret: string, form_url: string, status_url: string}
     */
    public static function esewa(): array
    {
        $mode = Setting::get('esewa_mode', 'test') === 'live' ? 'live' : 'test';

        return [
            'mode' => $mode,
            'merchant_id' => (string) Setting::get('esewa_merchant_id', ''),
            'product_code' => (string) Setting::get('esewa_product_code', ''),
            'secret_key' => static::secret('esewa_secret_key'),
            'merchant_secret' => static::secret('esewa_merchant_secret'),
            'form_url' => $mode === 'live'
                ? 'https://epay.esewa.com.np/api/epay/main/v2/form'
                : 'https://rc-epay.esewa.com.np/api/epay/main/v2/form',
            'status_url' => $mode === 'live'
                ? 'https://epay.esewa.com.np/api/epay/transaction/status/'
                : 'https://rc.esewa.com.np/api/epay/transaction/status/',
        ];
    }

    /**
     * Khalti configuration with mode-resolved endpoints.
     *
     * @return array{mode: string, public_key: string, secret_key: string, base_url: string}
     */
    public static function khalti(): array
    {
        $mode = Setting::get('khalti_mode', 'test') === 'live' ? 'live' : 'test';

        return [
            'mode' => $mode,
            'public_key' => (string) Setting::get('khalti_public_key', ''),
            'secret_key' => static::secret('khalti_secret_key'),
            'base_url' => $mode === 'live'
                ? 'https://khalti.com/api/v2/'
                : 'https://dev.khalti.com/api/v2/',
        ];
    }
}
