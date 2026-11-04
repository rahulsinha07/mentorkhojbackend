<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLastGrantedAtToMentorSessionCreditsTable extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('mentor_session_credits', 'last_granted_at')) {
            return;
        }

        Schema::table('mentor_session_credits', function (Blueprint $table) {
            $table->timestamp('last_granted_at')->nullable()->after('granted_by_admin_id');
        });
    }

    public function down()
    {
        if (Schema::hasColumn('mentor_session_credits', 'last_granted_at')) {
            Schema::table('mentor_session_credits', function (Blueprint $table) {
                $table->dropColumn('last_granted_at');
            });
        }
    }
}
