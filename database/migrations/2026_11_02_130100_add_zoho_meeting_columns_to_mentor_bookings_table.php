<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddZohoMeetingColumnsToMentorBookingsTable extends Migration
{
    public function up(): void
    {
        Schema::table('mentor_bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('mentor_bookings', 'zoho_meeting_id')) {
                $table->string('zoho_meeting_id')->nullable()->after('meeting_url');
            }
            if (!Schema::hasColumn('mentor_bookings', 'meeting_link_source')) {
                $table->string('meeting_link_source', 20)->nullable()->after('zoho_meeting_id');
            }
            if (!Schema::hasColumn('mentor_bookings', 'zoho_last_error')) {
                $table->text('zoho_last_error')->nullable()->after('meeting_link_source');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mentor_bookings', function (Blueprint $table) {
            $table->dropColumn(['zoho_meeting_id', 'meeting_link_source', 'zoho_last_error']);
        });
    }
}
