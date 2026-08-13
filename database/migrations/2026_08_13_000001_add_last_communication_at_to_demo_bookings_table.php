<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('demo_bookings')) {
            return;
        }

        Schema::table('demo_bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('demo_bookings', 'last_communication_at')) {
                $table->timestamp('last_communication_at')->nullable()->after('admin_notes');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('demo_bookings')) {
            return;
        }

        Schema::table('demo_bookings', function (Blueprint $table) {
            if (Schema::hasColumn('demo_bookings', 'last_communication_at')) {
                $table->dropColumn('last_communication_at');
            }
        });
    }
};
