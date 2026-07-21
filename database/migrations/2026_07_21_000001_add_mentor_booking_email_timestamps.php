<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mentor_bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('mentor_bookings', 'mentee_booked_email_sent_at')) {
                $table->timestamp('mentee_booked_email_sent_at')->nullable();
            }
            if (!Schema::hasColumn('mentor_bookings', 'mentor_notify_email_sent_at')) {
                $table->timestamp('mentor_notify_email_sent_at')->nullable();
            }
            if (!Schema::hasColumn('mentor_bookings', 'mentee_confirmed_email_sent_at')) {
                $table->timestamp('mentee_confirmed_email_sent_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('mentor_bookings', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('mentor_bookings', 'mentee_booked_email_sent_at') ? 'mentee_booked_email_sent_at' : null,
                Schema::hasColumn('mentor_bookings', 'mentor_notify_email_sent_at') ? 'mentor_notify_email_sent_at' : null,
                Schema::hasColumn('mentor_bookings', 'mentee_confirmed_email_sent_at') ? 'mentee_confirmed_email_sent_at' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
