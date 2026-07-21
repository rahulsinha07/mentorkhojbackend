<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seminars', function (Blueprint $table) {
            if (!Schema::hasColumn('seminars', 'fee_amount')) {
                $table->decimal('fee_amount', 10, 2)->default(0)->after('status');
            }
            if (!Schema::hasColumn('seminars', 'currency')) {
                $table->string('currency', 8)->default('INR')->after('fee_amount');
            }
        });

        if (!Schema::hasTable('seminar_bookings')) {
            Schema::create('seminar_bookings', function (Blueprint $table) {
                $table->id();
                $table->string('booking_ref')->unique();
                $table->foreignId('seminar_id')->constrained('seminars')->cascadeOnDelete();
                $table->unsignedBigInteger('customer_id');
                $table->string('name');
                $table->string('email');
                $table->string('phone', 20);
                $table->string('org')->nullable();
                $table->text('details')->nullable();
                $table->decimal('amount', 10, 2)->default(0);
                $table->string('currency', 8)->default('INR');
                $table->enum('status', ['pending', 'confirmed', 'cancelled'])->default('pending');
                $table->enum('payment_status', ['not_required', 'pending', 'paid', 'failed'])->default('not_required');
                $table->string('razorpay_order_id')->nullable();
                $table->string('razorpay_payment_id')->nullable();
                $table->string('razorpay_signature')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('email_sent_at')->nullable();
                $table->timestamps();

                $table->foreign('customer_id')->references('id')->on('users')->cascadeOnDelete();
                $table->index(['seminar_id', 'email']);
                $table->index(['customer_id', 'payment_status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seminar_bookings');

        Schema::table('seminars', function (Blueprint $table) {
            foreach (['fee_amount', 'currency'] as $col) {
                if (Schema::hasColumn('seminars', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
