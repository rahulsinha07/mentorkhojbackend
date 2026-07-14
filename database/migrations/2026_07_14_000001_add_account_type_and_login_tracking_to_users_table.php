<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('account_type', ['mentee', 'mentor'])->default('mentee')->after('login_medium');
            $table->enum('last_login_as', ['mentee', 'mentor'])->nullable()->after('account_type');
            $table->timestamp('last_login_at')->nullable()->after('last_login_as');
        });

        DB::table('users')->update(['account_type' => 'mentor']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['account_type', 'last_login_as', 'last_login_at']);
        });
    }
};
