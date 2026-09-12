<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE `support_tickets` MODIFY `requester_role` ENUM('customer', 'seller', 'delivery_agent', 'shipping_supervisor', 'travel_agency', 'marketer') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `support_tickets` MODIFY `requester_role` ENUM('customer', 'seller', 'delivery_agent', 'shipping_supervisor', 'travel_agency') NOT NULL");
    }
};
