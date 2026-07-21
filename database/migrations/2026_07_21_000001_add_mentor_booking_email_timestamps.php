<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mentor_bookings', function (Blueprint $table) {
            $table->timestamp('mentee_booked_email_sent_at')->nullable()->after('payment_status');
            $table->timestamp('mentor_notify_email_sent_at')->nullable()->after('mentee_booked_email_sent_at');
            $table->timestamp('mentee_confirmed_email_sent_at')->nullable()->after('mentor_notify_email_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('mentor_bookings', function (Blueprint $table) {
            $table->dropColumn([
                'mentee_booked_email_sent_at',
                'mentor_notify_email_sent_at',
                'mentee_confirmed_email_sent_at',
            ]);
        });
    }
};
