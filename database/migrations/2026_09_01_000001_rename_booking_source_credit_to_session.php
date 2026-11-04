<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('mentor_bookings')
            ->where('booking_source', 'credit')
            ->update(['booking_source' => 'session']);
    }

    public function down(): void
    {
        DB::table('mentor_bookings')
            ->where('booking_source', 'session')
            ->update(['booking_source' => 'credit']);
    }
};
