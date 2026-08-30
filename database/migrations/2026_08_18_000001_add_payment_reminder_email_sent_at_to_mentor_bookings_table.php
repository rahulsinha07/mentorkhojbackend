<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mentor_bookings')) {
            return;
        }

        Schema::table('mentor_bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('mentor_bookings', 'payment_reminder_email_sent_at')) {
                $table->timestamp('payment_reminder_email_sent_at')->nullable()->after('mentee_confirmed_email_sent_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('mentor_bookings')) {
            return;
        }

        Schema::table('mentor_bookings', function (Blueprint $table) {
            if (Schema::hasColumn('mentor_bookings', 'payment_reminder_email_sent_at')) {
                $table->dropColumn('payment_reminder_email_sent_at');
            }
        });
    }
};
