<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMentorShareTemplatesTable extends Migration
{
    public function up(): void
    {
        Schema::create('mentor_share_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('poster_image')->nullable();
            $table->text('default_caption')->nullable();
            $table->text('hashtags')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentor_share_templates');
    }
}
