<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsFeaturedToMentorsTable extends Migration
{
    public function up(): void
    {
        Schema::table('mentors', function (Blueprint $table) {
            if (!Schema::hasColumn('mentors', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('is_published');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mentors', function (Blueprint $table) {
            if (Schema::hasColumn('mentors', 'is_featured')) {
                $table->dropColumn('is_featured');
            }
        });
    }
}
