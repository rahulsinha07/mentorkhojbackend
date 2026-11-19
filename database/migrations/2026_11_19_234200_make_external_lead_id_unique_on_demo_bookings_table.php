<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MakeExternalLeadIdUniqueOnDemoBookingsTable extends Migration
{
    public function up(): void
    {
        if ($this->hasIndex('demo_bookings_external_lead_id_unique')) {
            return;
        }

        Schema::table('demo_bookings', function (Blueprint $table) {
            if ($this->hasIndex('demo_bookings_external_lead_id_index')) {
                $table->dropIndex('demo_bookings_external_lead_id_index');
            }
            $table->unique('external_lead_id');
        });
    }

    public function down(): void
    {
        if (!$this->hasIndex('demo_bookings_external_lead_id_unique')) {
            return;
        }

        Schema::table('demo_bookings', function (Blueprint $table) {
            $table->dropUnique('demo_bookings_external_lead_id_unique');
            $table->index('external_lead_id');
        });
    }

    private function hasIndex(string $name): bool
    {
        $rows = DB::select(
            'select 1 from information_schema.statistics where table_schema = database() and table_name = ? and index_name = ? limit 1',
            ['demo_bookings', $name]
        );

        return count($rows) > 0;
    }
}
