<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mentors', function (Blueprint $table) {
            if (!Schema::hasColumn('mentors', 'welcome_email_sent_at')) {
                $table->timestamp('welcome_email_sent_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('mentors', function (Blueprint $table) {
            if (Schema::hasColumn('mentors', 'welcome_email_sent_at')) {
                $table->dropColumn('welcome_email_sent_at');
            }
        });
    }
};
