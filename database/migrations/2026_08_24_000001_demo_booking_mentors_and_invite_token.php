<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DemoBookingMentorsAndInviteToken extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('demo_bookings') && !Schema::hasColumn('demo_bookings', 'profile_invite_token')) {
            Schema::table('demo_bookings', function (Blueprint $table) {
                $table->string('profile_invite_token', 64)->nullable()->unique()->after('user_id');
            });
        }

        if (!Schema::hasTable('demo_booking_mentors')) {
            Schema::create('demo_booking_mentors', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('demo_booking_id');
                $table->unsignedBigInteger('mentor_id');
                $table->timestamp('assigned_at')->nullable();
                $table->boolean('paid_session_done')->default(false);
                $table->timestamp('assignment_email_sent_at')->nullable();
                $table->timestamps();

                $table->unique(['demo_booking_id', 'mentor_id']);
                $table->foreign('demo_booking_id')->references('id')->on('demo_bookings')->cascadeOnDelete();
                $table->foreign('mentor_id')->references('id')->on('mentors')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_booking_mentors');
        if (Schema::hasTable('demo_bookings') && Schema::hasColumn('demo_bookings', 'profile_invite_token')) {
            Schema::table('demo_bookings', function (Blueprint $table) {
                $table->dropUnique(['profile_invite_token']);
                $table->dropColumn('profile_invite_token');
            });
        }
    }
}
