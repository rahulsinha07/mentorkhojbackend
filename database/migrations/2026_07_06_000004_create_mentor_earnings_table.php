<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMentorEarningsTable extends Migration
{
    public function up(): void
    {
        Schema::create('mentor_earnings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mentor_id');
            $table->unsignedBigInteger('mentor_booking_id')->nullable();
            $table->string('type', 32);
            $table->decimal('gross', 10, 2)->default(0);
            $table->decimal('fee', 10, 2)->default(0);
            $table->decimal('net', 10, 2)->default(0);
            $table->string('status', 32)->default('completed');
            $table->timestamps();

            $table->foreign('mentor_id')->references('id')->on('mentors')->cascadeOnDelete();
            $table->foreign('mentor_booking_id')->references('id')->on('mentor_bookings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentor_earnings');
    }
}
