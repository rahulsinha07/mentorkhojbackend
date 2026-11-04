<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMentorDocumentPurchasesTable extends Migration
{
    public function up(): void
    {
        Schema::create('mentor_document_purchases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mentor_id');
            $table->unsignedBigInteger('mentor_document_id');
            $table->unsignedBigInteger('mentee_user_id')->nullable();
            $table->unsignedBigInteger('legacy_order_id')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('platform_fee', 10, 2)->default(0);
            $table->decimal('mentor_net', 10, 2)->default(0);
            $table->string('payment_status', 32)->default('pending');
            $table->timestamps();

            $table->foreign('mentor_id')->references('id')->on('mentors')->cascadeOnDelete();
            $table->foreign('mentor_document_id')->references('id')->on('mentor_documents')->cascadeOnDelete();
            $table->foreign('mentee_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['mentee_user_id', 'payment_status']);
            $table->index('mentor_document_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentor_document_purchases');
    }
}
