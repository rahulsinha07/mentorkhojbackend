<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPlatformFeePercentToMentorSessionCreditsTable extends Migration
{
    public function up(): void
    {
        Schema::table('mentor_session_credits', function (Blueprint $table) {
            // Null = use the global default (MentorLogic::platformFeePercent()).
            // Set once, at pack creation, by the admin "Add sessions" form.
            $table->decimal('platform_fee_percent', 5, 2)->nullable()->after('fund_used');
        });
    }

    public function down(): void
    {
        Schema::table('mentor_session_credits', function (Blueprint $table) {
            $table->dropColumn('platform_fee_percent');
        });
    }
}
