<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMentorDocumentsTable extends Migration
{
    public function up(): void
    {
        Schema::create('mentor_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mentor_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('total_size_bytes')->default(0);
            $table->timestamps();

            $table->foreign('mentor_id')->references('id')->on('mentors')->cascadeOnDelete();
            $table->index(['mentor_id', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentor_documents');
    }
}
