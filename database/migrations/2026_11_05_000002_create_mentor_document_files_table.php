<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMentorDocumentFilesTable extends Migration
{
    public function up(): void
    {
        Schema::create('mentor_document_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mentor_document_id');
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('mentor_document_id')->references('id')->on('mentor_documents')->cascadeOnDelete();
            $table->index('mentor_document_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentor_document_files');
    }
}
