<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSeminarsTable extends Migration
{
    public function up(): void
    {
        Schema::create('seminars', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title');
            $table->string('tagline')->nullable();
            $table->text('blurb')->nullable();
            $table->string('date')->nullable();
            $table->string('mode', 32)->default('Online');
            $table->string('duration')->nullable();
            $table->string('audience')->nullable();
            $table->string('emoji', 16)->nullable();
            $table->json('highlights')->nullable();
            $table->string('status', 32)->default('active');
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_published', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seminars');
    }
}
