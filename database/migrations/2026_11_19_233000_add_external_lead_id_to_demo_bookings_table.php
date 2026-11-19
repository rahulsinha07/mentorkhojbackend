<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddExternalLeadIdToDemoBookingsTable extends Migration
{
    public function up(): void
    {
        Schema::table('demo_bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('demo_bookings', 'external_lead_id')) {
                $table->string('external_lead_id', 191)->nullable()->after('booking_ref')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('demo_bookings', function (Blueprint $table) {
            if (Schema::hasColumn('demo_bookings', 'external_lead_id')) {
                $table->dropColumn('external_lead_id');
            }
        });
    }
}
