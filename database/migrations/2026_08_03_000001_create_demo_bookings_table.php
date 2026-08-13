<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('demo_bookings')) {
            return;
        }

        Schema::create('demo_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_ref', 40)->unique();
            $table->string('name');
            $table->string('phone', 32);
            $table->string('email')->nullable();
            $table->string('category', 64)->index();
            $table->string('category_label')->nullable();
            $table->string('stage', 120);
            $table->json('subjects')->nullable();
            $table->string('source', 32)->default('lp')->index();
            $table->string('vertical', 64)->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_content')->nullable();
            $table->text('message')->nullable();
            $table->string('status', 32)->default('new')->index();
            $table->timestamp('email_sent_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('last_communication_at')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_bookings');
    }
};
