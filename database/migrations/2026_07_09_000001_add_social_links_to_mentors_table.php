<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mentors', function (Blueprint $table) {
            $table->text('social_links')->nullable()->after('share_short_url');
        });
    }

    public function down(): void
    {
        Schema::table('mentors', function (Blueprint $table) {
            $table->dropColumn('social_links');
        });
    }
};
