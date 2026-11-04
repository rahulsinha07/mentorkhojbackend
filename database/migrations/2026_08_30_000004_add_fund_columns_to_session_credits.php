<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mentor_session_credits')) {
            Schema::table('mentor_session_credits', function (Blueprint $table) {
                if (!Schema::hasColumn('mentor_session_credits', 'fund_total')) {
                    $table->decimal('fund_total', 12, 2)->default(0)->after('credits_used');
                }
                if (!Schema::hasColumn('mentor_session_credits', 'fund_used')) {
                    $table->decimal('fund_used', 12, 2)->default(0)->after('fund_total');
                }
            });
        }

        if (Schema::hasTable('mentor_session_credit_ledger')) {
            Schema::table('mentor_session_credit_ledger', function (Blueprint $table) {
                if (!Schema::hasColumn('mentor_session_credit_ledger', 'fund_gross')) {
                    $table->decimal('fund_gross', 12, 2)->nullable()->after('amount');
                }
                if (!Schema::hasColumn('mentor_session_credit_ledger', 'fund_net')) {
                    $table->decimal('fund_net', 12, 2)->nullable()->after('fund_gross');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mentor_session_credits')) {
            Schema::table('mentor_session_credits', function (Blueprint $table) {
                if (Schema::hasColumn('mentor_session_credits', 'fund_used')) {
                    $table->dropColumn('fund_used');
                }
                if (Schema::hasColumn('mentor_session_credits', 'fund_total')) {
                    $table->dropColumn('fund_total');
                }
            });
        }

        if (Schema::hasTable('mentor_session_credit_ledger')) {
            Schema::table('mentor_session_credit_ledger', function (Blueprint $table) {
                if (Schema::hasColumn('mentor_session_credit_ledger', 'fund_net')) {
                    $table->dropColumn('fund_net');
                }
                if (Schema::hasColumn('mentor_session_credit_ledger', 'fund_gross')) {
                    $table->dropColumn('fund_gross');
                }
            });
        }
    }
};
