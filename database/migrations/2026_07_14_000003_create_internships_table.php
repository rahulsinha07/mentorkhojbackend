<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInternshipsTable extends Migration
{
    public function up(): void
    {
        Schema::create('internships', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('role');
            $table->string('team')->nullable();
            $table->string('location')->nullable();
            $table->string('type', 32)->default('Remote');
            $table->string('duration')->nullable();
            $table->string('stipend')->nullable();
            $table->text('blurb')->nullable();
            $table->json('skills')->nullable();
            $table->string('status', 32)->default('active');
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_published', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internships');
    }
}
