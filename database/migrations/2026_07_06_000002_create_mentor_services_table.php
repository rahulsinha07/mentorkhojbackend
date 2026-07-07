<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMentorServicesTable extends Migration
{
    public function up(): void
    {
        Schema::create('mentor_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mentor_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_minutes')->default(30);
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('compare_at_price', 10, 2)->nullable();
            $table->string('badge', 32)->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_popular')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('meeting_type', 32)->default('video');
            $table->timestamps();

            $table->foreign('mentor_id')->references('id')->on('mentors')->cascadeOnDelete();
            $table->index(['mentor_id', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentor_services');
    }
}
