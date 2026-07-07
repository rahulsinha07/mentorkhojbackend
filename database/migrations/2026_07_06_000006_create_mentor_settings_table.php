<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMentorSettingsTable extends Migration
{
    public function up(): void
    {
        Schema::create('mentor_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mentor_id')->unique();
            $table->text('availability_json')->nullable();
            $table->text('notification_prefs')->nullable();
            $table->text('payout_details')->nullable();
            $table->text('share_prefs')->nullable();
            $table->timestamps();

            $table->foreign('mentor_id')->references('id')->on('mentors')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentor_settings');
    }
}
