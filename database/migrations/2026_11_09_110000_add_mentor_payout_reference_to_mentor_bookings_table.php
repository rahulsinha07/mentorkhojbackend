<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets admin record *how* a mentor was paid for a specific session — a UPI
 * transaction/reference id (e.g. the 12-digit UTR Google Pay shows after a
 * payment), or a bank/cash note — instead of just a paid/pending flag. This
 * is what lets a "2 sessions paid this week" claim actually be checked
 * against a real UPI reference later, instead of relying on memory.
 */
class AddMentorPayoutReferenceToMentorBookingsTable extends Migration
{
    public function up(): void
    {
        Schema::table('mentor_bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('mentor_bookings', 'mentor_payout_method')) {
                $table->string('mentor_payout_method', 20)->nullable()->after('mentor_paid_by_admin_id');
            }
            if (!Schema::hasColumn('mentor_bookings', 'mentor_payout_reference')) {
                $table->string('mentor_payout_reference', 255)->nullable()->after('mentor_payout_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mentor_bookings', function (Blueprint $table) {
            $table->dropColumn(['mentor_payout_method', 'mentor_payout_reference']);
        });
    }
}
