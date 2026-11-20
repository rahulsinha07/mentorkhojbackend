<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('exam_type')->default('jee_main');
            $table->unsignedSmallInteger('year');
            $table->string('session');
            $table->unsignedInteger('total_candidates');
            $table->string('source_note')->nullable();
            $table->timestamps();

            $table->unique(['exam_type', 'year', 'session']);
        });

        // Seed with publicly reported NTA attendance figures so the predictor
        // has real reference data from day one. Admins can add later sessions
        // from the admin panel without a deploy.
        DB::table('exam_sessions')->insert([
            [
                'exam_type' => 'jee_main',
                'year' => 2024,
                'session' => 'january',
                'total_candidates' => 1170036,
                'source_note' => 'NTA-reported Paper 1 (B.E./B.Tech) attendance, widely cited by Careers360.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'exam_type' => 'jee_main',
                'year' => 2024,
                'session' => 'april',
                'total_candidates' => 1067000,
                'source_note' => 'NTA-reported Paper 1 (B.E./B.Tech) attendance, widely cited by Careers360.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'exam_type' => 'jee_main',
                'year' => 2025,
                'session' => 'january',
                'total_candidates' => 1258136,
                'source_note' => 'NTA-reported Paper 1 (B.E./B.Tech) attendance.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'exam_type' => 'jee_main',
                'year' => 2025,
                'session' => 'april',
                'total_candidates' => 992350,
                'source_note' => 'NTA-reported Paper 1 (B.E./B.Tech) attendance.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_sessions');
    }
};
