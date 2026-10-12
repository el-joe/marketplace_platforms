<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/** P14: grace window (days) during which a replaced / timed-out / rejected invitation's code still credits its original marketer for earlier clicks. */
return new class extends Migration
{
    public function up(): void
    {
        Setting::firstOrCreate(
            ['key' => 'marketer_referral_grace_days'],
            [
                'value' => 7,
                'category' => 'marketer',
                'description' => 'Days a replaced, timed-out or rejected invitation referral code still credits the original marketer for clicks recorded before the replacement (no new attributions after it)',
                'is_encrypted' => 0,
                'is_public' => 0,
            ]
        );
        Setting::flush();
    }

    public function down(): void
    {
        Setting::where('key', 'marketer_referral_grace_days')->delete();
        Setting::flush();
    }
};
