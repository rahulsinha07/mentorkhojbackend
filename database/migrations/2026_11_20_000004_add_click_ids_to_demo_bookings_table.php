<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddClickIdsToDemoBookingsTable extends Migration
{
    public function up(): void
    {
        Schema::table('demo_bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('demo_bookings', 'gclid')) {
                $table->string('gclid', 191)->nullable()->after('utm_content')->index();
            }
            if (!Schema::hasColumn('demo_bookings', 'wbraid')) {
                $table->string('wbraid', 191)->nullable()->after('gclid');
            }
            if (!Schema::hasColumn('demo_bookings', 'gbraid')) {
                $table->string('gbraid', 191)->nullable()->after('wbraid');
            }
        });
    }

    public function down(): void
    {
        Schema::table('demo_bookings', function (Blueprint $table) {
            foreach (['gclid', 'wbraid', 'gbraid'] as $column) {
                if (Schema::hasColumn('demo_bookings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
