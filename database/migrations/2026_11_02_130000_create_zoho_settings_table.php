<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateZohoSettingsTable extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('zoho_settings')) {
            Schema::create('zoho_settings', function (Blueprint $table) {
                $table->id();
                $table->string('client_id')->nullable();
                $table->text('client_secret')->nullable();
                $table->text('access_token')->nullable();
                $table->text('refresh_token')->nullable();
                $table->timestamp('access_token_expires_at')->nullable();
                $table->string('zoho_account_id')->nullable();
                $table->string('data_center', 10)->default('in');
                $table->string('status', 20)->default('disconnected');
                $table->text('last_error')->nullable();
                $table->timestamp('connected_at')->nullable();
                $table->unsignedBigInteger('connected_by_admin_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('zoho_settings');
    }
}
