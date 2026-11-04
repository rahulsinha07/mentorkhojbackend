<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaymentStatusToMentorSessionCreditsTable extends Migration
{
    public function up(): void
    {
        Schema::table('mentor_session_credits', function (Blueprint $table) {
            if (!Schema::hasColumn('mentor_session_credits', 'payment_status')) {
                $table->string('payment_status', 16)->default('pending')->after('platform_fee_percent');
            }
            if (!Schema::hasColumn('mentor_session_credits', 'payment_confirmed_at')) {
                $table->timestamp('payment_confirmed_at')->nullable()->after('payment_status');
            }
            if (!Schema::hasColumn('mentor_session_credits', 'payment_confirmed_by_admin_id')) {
                $table->unsignedBigInteger('payment_confirmed_by_admin_id')->nullable()->after('payment_confirmed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mentor_session_credits', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'payment_confirmed_at', 'payment_confirmed_by_admin_id']);
        });
    }
}
