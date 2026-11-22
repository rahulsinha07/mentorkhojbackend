<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the admin's send-history list show which audience (mentor/mentee) a
 * past broadcast targeted. Nullable since historical rows predate audience
 * targeting — they just show blank.
 */
class AddAudienceToNotificationsTable extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('notifications', 'audience')) {
                $table->string('audience', 10)->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('audience');
        });
    }
}
