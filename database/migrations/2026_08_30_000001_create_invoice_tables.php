<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoiceTables extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('invoice_settings')) {
            Schema::create('invoice_settings', function (Blueprint $table) {
                $table->id();
                $table->string('logo')->nullable();
                $table->string('bank_name')->nullable();
                $table->string('account_name')->nullable();
                $table->string('account_number')->nullable();
                $table->string('ifsc')->nullable();
                $table->string('bank_branch')->nullable();
                $table->string('upi_id')->nullable();
                $table->text('footer_text')->nullable();
                $table->text('default_terms')->nullable();
                $table->text('default_notes')->nullable();
                $table->string('invoice_prefix', 32)->default('MK-INV');
                $table->unsignedSmallInteger('number_padding')->default(6);
                $table->unsignedBigInteger('next_sequence')->default(1);
                $table->string('default_currency', 8)->default('INR');
                $table->string('default_tax_mode', 32)->default('cgst_sgst');
                $table->decimal('default_tax_rate', 8, 2)->default(18);
                $table->string('brand_color', 16)->default('#107980');
                $table->unsignedSmallInteger('default_payment_terms_days')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('invoice_customers')) {
            Schema::create('invoice_customers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('name');
                $table->string('customer_type', 32)->nullable();
                $table->string('company_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 32)->nullable();
                $table->text('billing_address')->nullable();
                $table->string('billing_city')->nullable();
                $table->string('billing_state')->nullable();
                $table->string('billing_country')->nullable();
                $table->string('billing_postal_code', 16)->nullable();
                $table->text('shipping_address')->nullable();
                $table->string('shipping_city')->nullable();
                $table->string('shipping_state')->nullable();
                $table->string('shipping_country')->nullable();
                $table->string('shipping_postal_code', 16)->nullable();
                $table->string('gstin', 20)->nullable();
                $table->string('pan', 16)->nullable();
                $table->string('external_customer_id', 64)->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                $table->index('email');
                $table->index('phone');
            });
        }

        if (!Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->id();
                $table->string('invoice_number', 64)->unique();
                $table->string('status', 32)->default('draft');
                $table->date('invoice_date');
                $table->date('due_date')->nullable();
                $table->date('payment_date')->nullable();
                $table->string('currency', 8)->default('INR');
                $table->string('place_of_supply')->nullable();
                $table->string('reference_number')->nullable();
                $table->string('tax_mode', 32)->default('none');

                $table->unsignedBigInteger('invoice_customer_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('source_type', 32)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();

                $table->string('customer_name');
                $table->string('customer_type', 32)->nullable();
                $table->string('customer_company')->nullable();
                $table->string('customer_email')->nullable();
                $table->string('customer_phone', 32)->nullable();
                $table->text('billing_address')->nullable();
                $table->string('billing_city')->nullable();
                $table->string('billing_state')->nullable();
                $table->string('billing_country')->nullable();
                $table->string('billing_postal_code', 16)->nullable();
                $table->text('shipping_address')->nullable();
                $table->string('shipping_city')->nullable();
                $table->string('shipping_state')->nullable();
                $table->string('shipping_country')->nullable();
                $table->string('shipping_postal_code', 16)->nullable();
                $table->string('customer_gstin', 20)->nullable();
                $table->string('customer_pan', 16)->nullable();
                $table->string('customer_external_id', 64)->nullable();

                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('discount_total', 14, 2)->default(0);
                $table->decimal('taxable_amount', 14, 2)->default(0);
                $table->decimal('cgst', 14, 2)->default(0);
                $table->decimal('sgst', 14, 2)->default(0);
                $table->decimal('igst', 14, 2)->default(0);
                $table->decimal('other_tax', 14, 2)->default(0);
                $table->decimal('additional_charges', 14, 2)->default(0);
                $table->decimal('round_off', 14, 2)->default(0);
                $table->decimal('total_amount', 14, 2)->default(0);
                $table->decimal('amount_paid', 14, 2)->default(0);
                $table->decimal('balance_due', 14, 2)->default(0);

                $table->string('payment_status', 32)->default('pending');
                $table->string('payment_method', 32)->nullable();
                $table->string('transaction_id')->nullable();
                $table->text('customer_notes')->nullable();
                $table->text('terms')->nullable();
                $table->boolean('invoice_number_manual')->default(false);

                $table->unsignedBigInteger('created_by_admin_id')->nullable();
                $table->unsignedBigInteger('updated_by_admin_id')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('invoice_customer_id')->references('id')->on('invoice_customers')->nullOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                $table->index(['status', 'invoice_date']);
                $table->index('payment_status');
                $table->index('user_id');
                $table->index(['source_type', 'source_id']);
            });
        }

        if (!Schema::hasTable('invoice_items')) {
            Schema::create('invoice_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('invoice_id');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->string('service_name');
                $table->text('description')->nullable();
                $table->string('sku', 64)->nullable();
                $table->decimal('quantity', 10, 2)->default(1);
                $table->string('unit', 32)->nullable();
                $table->decimal('unit_price', 14, 2)->default(0);
                $table->decimal('discount', 14, 2)->default(0);
                $table->string('discount_type', 16)->default('fixed');
                $table->decimal('tax_rate', 8, 2)->default(0);
                $table->string('tax_type', 32)->nullable();
                $table->decimal('tax_amount', 14, 2)->default(0);
                $table->decimal('line_total', 14, 2)->default(0);
                $table->timestamps();

                $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
                $table->index(['invoice_id', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_customers');
        Schema::dropIfExists('invoice_settings');
    }
}
