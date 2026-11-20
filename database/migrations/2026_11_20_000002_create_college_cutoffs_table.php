<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('college_cutoffs', function (Blueprint $table) {
            $table->id();
            $table->string('exam_type')->default('jee_main');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('round');
            $table->string('institute_type'); // IIT | NIT | IIIT | IIEST | GFTI (derived at import)
            $table->string('institute_name');
            $table->string('program');
            $table->string('quota', 4); // AI | HS | OS | GO | JK | LA (as published by JoSAA)
            $table->string('category', 16); // open | ews | obc_ncl | sc | st
            $table->boolean('is_pwd')->default(false);
            $table->string('gender', 16); // gender_neutral | female
            $table->unsignedInteger('opening_rank')->nullable();
            $table->unsignedInteger('closing_rank')->nullable();
            $table->boolean('is_preparatory')->default(false); // 'P' suffix — Preparatory Rank List
            $table->timestamps();

            $table->index(['exam_type', 'year', 'round']);
            $table->index(['category', 'gender', 'closing_rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('college_cutoffs');
    }
};
