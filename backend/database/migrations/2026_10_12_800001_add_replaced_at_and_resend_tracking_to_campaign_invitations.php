<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketer_campaign_invitations', function (Blueprint $table) {
            // P14 follow-up: the invitation keeps its real outcome status (rejected / timed_out) and is only
            // stamped when a replacement invitation took over its ad.
            $table->timestamp('replaced_at')->nullable()->after('replaced_invitation_id');
            // P10 follow-up: admin "Resend" WhatsApp action (cooldown anchor + counter).
            $table->timestamp('whatsapp_resent_at')->nullable()->after('whatsapp_failed_at');
            $table->unsignedSmallInteger('whatsapp_resend_count')->default(0)->after('whatsapp_resent_at');
        });

        foreach ([
            ['marketer_whatsapp_resend_cooldown_minutes', 10, 'Minutes an admin must wait before re-sending the WhatsApp invitation of the same pending invitation'],
            ['marketer_peer_campaign_min_stock', 1, 'Minimum available units an influencer own-FBN listing must hold before another influencer can promote it'],
        ] as [$key, $value, $description]) {
            Setting::firstOrCreate(
                ['key' => $key],
                ['value' => $value, 'category' => 'marketer', 'description' => $description, 'is_encrypted' => 0, 'is_public' => 0]
            );
        }
        Setting::flush();
    }

    public function down(): void
    {
        Schema::table('marketer_campaign_invitations', function (Blueprint $table) {
            $table->dropColumn(['replaced_at', 'whatsapp_resent_at', 'whatsapp_resend_count']);
        });
        Setting::whereIn('key', ['marketer_whatsapp_resend_cooldown_minutes', 'marketer_peer_campaign_min_stock'])->delete();
        Setting::flush();
    }
};
