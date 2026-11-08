<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-session mentor payout tracking — separate from (a) the student/pack
 * payment status (MentorSessionCredit::payment_status) and (b) session
 * completion status (MentorBooking::status). Before this, there was no way
 * to know whether a specific completed session's earning had actually been
 * paid out to the mentor.
 *
 * Backfill: every session that is ALREADY completed as of this migration is
 * marked 'paid', since under the old process "completed" was the de facto
 * signal that the mentor had been settled. Only sessions completed AFTER
 * this migration start as 'pending' and require an explicit admin action.
 */
class AddMentorPayoutStatusToMentorBookingsTable extends Migration
{
    public function up(): void
    {
        Schema::table('mentor_bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('mentor_bookings', 'mentor_payout_status')) {
                $table->string('mentor_payout_status', 20)->default('pending')->after('mentor_net');
            }
            if (!Schema::hasColumn('mentor_bookings', 'mentor_paid_at')) {
                $table->timestamp('mentor_paid_at')->nullable()->after('mentor_payout_status');
            }
            if (!Schema::hasColumn('mentor_bookings', 'mentor_paid_by_admin_id')) {
                $table->unsignedBigInteger('mentor_paid_by_admin_id')->nullable()->after('mentor_paid_at');
            }
        });

        DB::table('mentor_bookings')
            ->where('status', 'completed')
            ->update([
                'mentor_payout_status' => 'paid',
                'mentor_paid_at' => DB::raw('COALESCE(completed_at, updated_at)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('mentor_bookings', function (Blueprint $table) {
            $table->dropColumn(['mentor_payout_status', 'mentor_paid_at', 'mentor_paid_by_admin_id']);
        });
    }
}
