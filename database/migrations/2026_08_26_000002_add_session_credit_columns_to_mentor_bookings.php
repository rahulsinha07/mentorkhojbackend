<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddSessionCreditColumnsToMentorBookings extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mentor_bookings')) {
            return;
        }

        Schema::table('mentor_bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('mentor_bookings', 'session_credit_id')) {
                $table->unsignedBigInteger('session_credit_id')->nullable()->after('mentee_user_id');
            }
            if (!Schema::hasColumn('mentor_bookings', 'booking_source')) {
                $table->string('booking_source', 32)->default('paid')->after('payment_status');
            }
            if (!Schema::hasColumn('mentor_bookings', 'session_reminder_24h_sent_at')) {
                $table->timestamp('session_reminder_24h_sent_at')->nullable()->after('schedule_notify_sent_at');
            }
        });

        if (Schema::hasColumn('mentor_bookings', 'session_credit_id')
            && Schema::hasTable('mentor_session_credits')
            && !$this->foreignKeyExists('mentor_bookings', 'mentor_bookings_session_credit_id_foreign')) {
            Schema::table('mentor_bookings', function (Blueprint $table) {
                $table->foreign('session_credit_id')
                    ->references('id')
                    ->on('mentor_session_credits')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('mentor_bookings')) {
            return;
        }

        Schema::table('mentor_bookings', function (Blueprint $table) {
            if (Schema::hasColumn('mentor_bookings', 'session_credit_id')) {
                try {
                    $table->dropForeign(['session_credit_id']);
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            $cols = [];
            foreach (['session_credit_id', 'booking_source', 'session_reminder_24h_sent_at'] as $col) {
                if (Schema::hasColumn('mentor_bookings', $col)) {
                    $cols[] = $col;
                }
            }
            if ($cols) {
                $table->dropColumn($cols);
            }
        });
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        try {
            $db = Schema::getConnection()->getDatabaseName();
            $row = DB::selectOne(
                'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
                [$db, $table, $name, 'FOREIGN KEY']
            );

            return $row !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
