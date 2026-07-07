<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMentorShareLogsTable extends Migration
{
    public function up(): void
    {
        Schema::create('mentor_share_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mentor_id');
            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('channel', 32);
            $table->string('profile_url')->nullable();
            $table->timestamps();

            $table->foreign('mentor_id')->references('id')->on('mentors')->cascadeOnDelete();
            $table->foreign('template_id')->references('id')->on('mentor_share_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentor_share_logs');
    }
}
