<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const KEYS = [
        'mail_mailer' => ['smtp', 'Mail driver (smtp/log/sendmail)', false],
        'mail_host' => ['', 'SMTP host', false],
        'mail_port' => [587, 'SMTP port', false],
        'mail_encryption' => ['tls', 'SMTP encryption (tls/ssl/none)', false],
        'mail_username' => ['', 'SMTP username', false],
        'mail_password' => ['', 'SMTP password', true],
        'mail_from_address' => ['', 'Sender email address', false],
        'mail_from_name' => ['', 'Sender display name', false],
    ];

    public function up(): void
    {
        foreach (self::KEYS as $key => [$value, $description, $encrypted]) {
            if (! Setting::where('key', $key)->exists()) {
                Setting::create([
                    'key' => $key,
                    'value' => $value,
                    'category' => 'integrations',
                    'description' => $description,
                    'is_encrypted' => $encrypted,
                    'is_public' => false,
                ]);
            }
        }
    }

    public function down(): void
    {
        Setting::whereIn('key', array_keys(self::KEYS))->delete();
    }
};
