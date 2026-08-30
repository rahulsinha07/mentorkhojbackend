<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMentorSessionCreditsTables extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mentor_session_credits')) {
            Schema::create('mentor_session_credits', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mentee_user_id');
                $table->unsignedBigInteger('mentor_id');
                $table->unsignedInteger('credits_total')->default(0);
                $table->unsignedInteger('credits_used')->default(0);
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('granted_by_admin_id')->nullable();
                $table->timestamps();

                $table->unique(['mentee_user_id', 'mentor_id'], 'mentor_session_credits_pair_unique');
                $table->foreign('mentee_user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('mentor_id')->references('id')->on('mentors')->cascadeOnDelete();
                $table->index('mentor_id');
            });
        }

        if (!Schema::hasTable('mentor_session_credit_ledger')) {
            Schema::create('mentor_session_credit_ledger', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('credit_id');
                $table->string('type', 32);
                $table->unsignedInteger('amount')->default(1);
                $table->unsignedBigInteger('mentor_booking_id')->nullable();
                $table->unsignedBigInteger('admin_id')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->foreign('credit_id')->references('id')->on('mentor_session_credits')->cascadeOnDelete();
                $table->foreign('mentor_booking_id')->references('id')->on('mentor_bookings')->nullOnDelete();
                $table->unique(['mentor_booking_id', 'type'], 'credit_ledger_booking_type_unique');
                $table->index(['credit_id', 'type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mentor_session_credit_ledger');
        Schema::dropIfExists('mentor_session_credits');
    }
}
