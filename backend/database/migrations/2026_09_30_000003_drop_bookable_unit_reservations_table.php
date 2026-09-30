<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('bookable_unit_reservations');
    }

    public function down(): void
    {
        // Restoration handled by the original create migration if needed.
    }
};
