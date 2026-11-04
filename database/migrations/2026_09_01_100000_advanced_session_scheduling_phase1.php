<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mentor_bookings')) {
            Schema::table('mentor_bookings', function (Blueprint $table) {
                foreach ([
                    'session_number' => fn () => $table->unsignedInteger('session_number')->nullable()->after('session_credit_id'),
                    'total_sessions' => fn () => $table->unsignedInteger('total_sessions')->nullable()->after('session_number'),
                    'duration_minutes' => fn () => $table->unsignedInteger('duration_minutes')->nullable()->after('total_sessions'),
                    'session_type' => fn () => $table->string('session_type', 32)->nullable()->after('duration_minutes'),
                    'timezone' => fn () => $table->string('timezone', 64)->nullable()->after('session_type'),
                    'scheduled_at' => fn () => $table->dateTime('scheduled_at')->nullable()->after('preferred_time'),
                    'meeting_url' => fn () => $table->string('meeting_url', 512)->nullable()->after('scheduled_at'),
                    'mentor_notes' => fn () => $table->text('mentor_notes')->nullable()->after('mentee_note'),
                    'mentee_notes' => fn () => $table->text('mentee_notes')->nullable()->after('mentor_notes'),
                    'cancellation_reason' => fn () => $table->text('cancellation_reason')->nullable(),
                    'cancelled_by_user_id' => fn () => $table->unsignedBigInteger('cancelled_by_user_id')->nullable(),
                    'cancelled_at' => fn () => $table->timestamp('cancelled_at')->nullable(),
                    'reschedule_reason' => fn () => $table->text('reschedule_reason')->nullable(),
                    'reschedule_requested_by_user_id' => fn () => $table->unsignedBigInteger('reschedule_requested_by_user_id')->nullable(),
                    'reschedule_requested_at' => fn () => $table->timestamp('reschedule_requested_at')->nullable(),
                    'reschedule_proposed_date' => fn () => $table->date('reschedule_proposed_date')->nullable(),
                    'reschedule_proposed_time' => fn () => $table->time('reschedule_proposed_time')->nullable(),
                    'completed_by_user_id' => fn () => $table->unsignedBigInteger('completed_by_user_id')->nullable(),
                    'completed_at' => fn () => $table->timestamp('completed_at')->nullable(),
                    'session_reminder_1h_sent_at' => fn () => $table->timestamp('session_reminder_1h_sent_at')->nullable(),
                ] as $col => $adder) {
                    if (!Schema::hasColumn('mentor_bookings', $col)) {
                        $adder();
                    }
                }
            });
        }

        if (!Schema::hasTable('mentor_booking_events')) {
            Schema::create('mentor_booking_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mentor_booking_id');
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->string('event_type', 64);
                $table->json('payload')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->foreign('mentor_booking_id')->references('id')->on('mentor_bookings')->cascadeOnDelete();
                $table->index(['mentor_booking_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('mentor_session_notifications')) {
            Schema::create('mentor_session_notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mentor_booking_id')->nullable();
                $table->unsignedBigInteger('session_credit_id')->nullable();
                $table->unsignedBigInteger('recipient_user_id')->nullable();
                $table->string('recipient_email', 255)->nullable();
                $table->string('notification_type', 64);
                $table->timestamp('scheduled_for')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->string('status', 32)->default('pending');
                $table->text('error_message')->nullable();
                $table->timestamps();
                $table->index(['mentor_booking_id', 'recipient_user_id', 'notification_type'], 'session_notif_lookup');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mentor_session_notifications');
        Schema::dropIfExists('mentor_booking_events');
    }
};
