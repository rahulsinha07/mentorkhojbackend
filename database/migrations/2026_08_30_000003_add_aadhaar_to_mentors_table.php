<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mentors', function (Blueprint $table) {
            if (!Schema::hasColumn('mentors', 'aadhaar')) {
                $table->string('aadhaar', 12)->nullable()->after('social_links');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mentors', function (Blueprint $table) {
            if (Schema::hasColumn('mentors', 'aadhaar')) {
                $table->dropColumn('aadhaar');
            }
        });
    }
};
