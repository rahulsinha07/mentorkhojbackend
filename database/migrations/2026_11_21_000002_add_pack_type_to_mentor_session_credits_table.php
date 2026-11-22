<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddPackTypeToMentorSessionCreditsTable extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('mentor_session_credits', 'pack_type')) {
            Schema::table('mentor_session_credits', function (Blueprint $table) {
                // 'paid' | 'demo' | 'free'. Existing rows default to 'paid'
                // (they already are). Keeping demo/free in their own row per
                // mentee+mentor means their zero-amount fund_total never
                // mixes into — and dilutes — an existing paid pack's math.
                $table->string('pack_type', 16)->default('paid')->after('mentor_id');
            });
        }

        // Add the new composite unique FIRST — its leftmost column
        // (mentee_user_id) is also what the mentee_user_id foreign key
        // needs a covering index for. Only once that's in place can the
        // old pair_unique index be dropped without MySQL refusing (error
        // 1553: index needed in a foreign key constraint).
        if (!$this->indexExists('mentor_session_credits', 'mentor_session_credits_pair_type_unique')) {
            Schema::table('mentor_session_credits', function (Blueprint $table) {
                $table->unique(['mentee_user_id', 'mentor_id', 'pack_type'], 'mentor_session_credits_pair_type_unique');
            });
        }

        if ($this->indexExists('mentor_session_credits', 'mentor_session_credits_pair_unique')) {
            Schema::table('mentor_session_credits', function (Blueprint $table) {
                $table->dropUnique('mentor_session_credits_pair_unique');
            });
        }
    }

    public function down(): void
    {
        // Same ordering constraint in reverse: add the old unique back
        // before dropping the new one, so mentee_user_id always has a
        // covering index.
        if (!$this->indexExists('mentor_session_credits', 'mentor_session_credits_pair_unique')) {
            Schema::table('mentor_session_credits', function (Blueprint $table) {
                $table->unique(['mentee_user_id', 'mentor_id'], 'mentor_session_credits_pair_unique');
            });
        }

        if ($this->indexExists('mentor_session_credits', 'mentor_session_credits_pair_type_unique')) {
            Schema::table('mentor_session_credits', function (Blueprint $table) {
                $table->dropUnique('mentor_session_credits_pair_type_unique');
            });
        }

        if (Schema::hasColumn('mentor_session_credits', 'pack_type')) {
            Schema::table('mentor_session_credits', function (Blueprint $table) {
                $table->dropColumn('pack_type');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $rows = DB::select('SHOW INDEX FROM ' . $table . ' WHERE Key_name = ?', [$indexName]);

        return count($rows) > 0;
    }
}
