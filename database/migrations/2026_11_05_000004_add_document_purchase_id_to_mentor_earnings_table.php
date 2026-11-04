<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDocumentPurchaseIdToMentorEarningsTable extends Migration
{
    public function up(): void
    {
        Schema::table('mentor_earnings', function (Blueprint $table) {
            $table->unsignedBigInteger('mentor_document_purchase_id')->nullable()->after('mentor_booking_id');
            $table->foreign('mentor_document_purchase_id')->references('id')->on('mentor_document_purchases')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mentor_earnings', function (Blueprint $table) {
            $table->dropForeign(['mentor_document_purchase_id']);
            $table->dropColumn('mentor_document_purchase_id');
        });
    }
}
