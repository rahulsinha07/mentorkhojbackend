<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInvoiceDemoPrivacyFields extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (!Schema::hasColumn('invoices', 'customer_aadhaar')) {
                    $table->string('customer_aadhaar', 12)->nullable()->after('customer_pan');
                }
                if (!Schema::hasColumn('invoices', 'classes_booked')) {
                    $table->unsignedInteger('classes_booked')->nullable()->after('customer_aadhaar');
                }
                if (!Schema::hasColumn('invoices', 'mentor_snapshot')) {
                    $table->json('mentor_snapshot')->nullable()->after('classes_booked');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn(['customer_aadhaar', 'classes_booked', 'mentor_snapshot']);
            });
        }
    }
}
