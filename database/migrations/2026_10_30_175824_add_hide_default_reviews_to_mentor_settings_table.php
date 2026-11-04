<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHideDefaultReviewsToMentorSettingsTable extends Migration
{
    public function up(): void
    {
        Schema::table('mentor_settings', function (Blueprint $table) {
            $table->boolean('hide_default_reviews')->default(false)->after('share_prefs');
        });
    }

    public function down(): void
    {
        Schema::table('mentor_settings', function (Blueprint $table) {
            $table->dropColumn('hide_default_reviews');
        });
    }
}
