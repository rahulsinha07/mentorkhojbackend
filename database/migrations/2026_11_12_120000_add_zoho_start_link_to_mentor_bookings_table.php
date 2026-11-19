<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddZohoStartLinkToMentorBookingsTable extends Migration
{
    public function up(): void
    {
        Schema::table('mentor_bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('mentor_bookings', 'zoho_start_link')) {
                $table->string('zoho_start_link', 1024)->nullable()->after('zoho_meeting_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mentor_bookings', function (Blueprint $table) {
            if (Schema::hasColumn('mentor_bookings', 'zoho_start_link')) {
                $table->dropColumn('zoho_start_link');
            }
        });
    }
}
