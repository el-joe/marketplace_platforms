<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('vendor_campaign_invitations');
        Schema::dropIfExists('vendor_campaign_offer_products');
        Schema::dropIfExists('vendor_campaign_offers');
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void {}
};
