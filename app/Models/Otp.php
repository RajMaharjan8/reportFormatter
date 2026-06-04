<?php

namespace App\Models;

use App\Mail\OtpCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class Otp extends Model
{
    public const PURPOSE_REGISTRATION = 'registration';

    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    /** Minutes a freshly issued code stays valid. */
    public const EXPIRY_MINUTES = 10;

    /** Minimum seconds between two sends to the same address/purpose. */
    public const RESEND_COOLDOWN_SECONDS = 60;

    protected $fillable = ['email', 'purpose', 'code_hash', 'expires_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Issue (or replace) a code for the address/purpose and email it. Returns
     * the plain code so tests can assert against it; production never exposes it.
     */
    public static function send(string $email, string $purpose): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        static::updateOrCreate(
            ['email' => $email, 'purpose' => $purpose],
            ['code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES)],
        );

        Mail::to($email)->send(new OtpCode($code, $purpose));

        return $code;
    }

    /**
     * Validate a submitted code and consume it on success.
     */
    public static function consume(string $email, string $purpose, string $code): bool
    {
        $otp = static::where('email', $email)->where('purpose', $purpose)->first();

        if ($otp === null || $otp->expires_at->isPast() || ! Hash::check($code, $otp->code_hash)) {
            return false;
        }

        $otp->delete();

        return true;
    }

    /**
     * Whether enough time has passed to send another code (resend throttle).
     */
    public static function canSend(string $email, string $purpose): bool
    {
        $otp = static::where('email', $email)->where('purpose', $purpose)->first();

        return $otp === null
            || $otp->updated_at->lt(now()->subSeconds(self::RESEND_COOLDOWN_SECONDS));
    }
}
