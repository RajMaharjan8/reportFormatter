<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class MailConfigServiceProvider extends ServiceProvider
{
    /**
     * Apply the admin-configured SMTP settings over the file config at
     * runtime. This runs on every request, so it survives `config:cache`
     * (which only freezes the config/*.php files, not these overrides).
     */
    public function boot(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }

            $settings = Setting::map();
        } catch (\Throwable) {
            // DB not ready yet (fresh install, migrate, broken connection).
            return;
        }

        if (($settings['mail_host'] ?? '') === '') {
            return;
        }

        // config/mail.php uses a "scheme" (smtp/smtps), not "encryption".
        $scheme = ($settings['mail_encryption'] ?? null) === 'ssl' ? 'smtps' : null;

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.scheme' => $scheme,
            'mail.mailers.smtp.host' => $settings['mail_host'],
            'mail.mailers.smtp.port' => (int) ($settings['mail_port'] ?? 587),
            'mail.mailers.smtp.username' => $settings['mail_username'] ?? null,
            'mail.mailers.smtp.password' => $settings['mail_password'] ?? null,
            'mail.from.address' => ($settings['mail_from_address'] ?? '') ?: config('mail.from.address'),
            'mail.from.name' => ($settings['mail_from_name'] ?? '') ?: config('mail.from.name'),
        ]);
    }
}
