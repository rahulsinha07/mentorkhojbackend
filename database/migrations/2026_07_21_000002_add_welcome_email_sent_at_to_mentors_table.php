<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mentors', function (Blueprint $table) {
            $table->timestamp('welcome_email_sent_at')->nullable()->after('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('mentors', function (Blueprint $table) {
            $table->dropColumn('welcome_email_sent_at');
        });
    }
};
