<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMentorBookingsTable extends Migration
{
    public function up(): void
    {
        Schema::create('mentor_bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mentor_id');
            $table->unsignedBigInteger('mentor_service_id');
            $table->unsignedBigInteger('mentee_user_id')->nullable();
            $table->unsignedBigInteger('legacy_order_id')->nullable();
            $table->date('preferred_date')->nullable();
            $table->time('preferred_time')->nullable();
            $table->text('mentee_note')->nullable();
            $table->string('status', 32)->default('requested');
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('platform_fee', 10, 2)->default(0);
            $table->decimal('mentor_net', 10, 2)->default(0);
            $table->string('payment_status', 32)->default('pending');
            $table->timestamps();

            $table->foreign('mentor_id')->references('id')->on('mentors')->cascadeOnDelete();
            $table->foreign('mentor_service_id')->references('id')->on('mentor_services')->cascadeOnDelete();
            $table->foreign('mentee_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['mentor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentor_bookings');
    }
}
