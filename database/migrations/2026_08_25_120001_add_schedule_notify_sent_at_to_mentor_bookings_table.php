<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddScheduleNotifySentAtToMentorBookingsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('mentor_bookings') || Schema::hasColumn('mentor_bookings', 'schedule_notify_sent_at')) {
            return;
        }

        Schema::table('mentor_bookings', function (Blueprint $table) {
            $table->timestamp('schedule_notify_sent_at')->nullable()->after('mentee_confirmed_email_sent_at');
        });
    }

    public function down()
    {
        if (Schema::hasColumn('mentor_bookings', 'schedule_notify_sent_at')) {
            Schema::table('mentor_bookings', function (Blueprint $table) {
                $table->dropColumn('schedule_notify_sent_at');
            });
        }
    }
}
