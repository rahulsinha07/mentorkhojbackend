<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMentorPayoutsTable extends Migration
{
    public function up(): void
    {
        Schema::create('mentor_payouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mentor_id');
            $table->decimal('amount', 10, 2);
            $table->string('method', 64)->nullable();
            $table->text('bank_details')->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamps();

            $table->foreign('mentor_id')->references('id')->on('mentors')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentor_payouts');
    }
}
