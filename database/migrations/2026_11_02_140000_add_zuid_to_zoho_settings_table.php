<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddZuidToZohoSettingsTable extends Migration
{
    public function up(): void
    {
        Schema::table('zoho_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('zoho_settings', 'zoho_presenter_zuid')) {
                $table->string('zoho_presenter_zuid')->nullable()->after('zoho_account_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('zoho_settings', function (Blueprint $table) {
            $table->dropColumn('zoho_presenter_zuid');
        });
    }
}
