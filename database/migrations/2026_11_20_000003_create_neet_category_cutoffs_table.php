<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('neet_category_cutoffs', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->string('category', 16); // general | ews | obc_ncl | sc | st
            $table->unsignedTinyInteger('percentile'); // fixed by NMC norms: 50 (general), 40 (obc/sc/st)
            $table->unsignedSmallInteger('cutoff_marks_low');
            $table->unsignedSmallInteger('cutoff_marks_high');
            $table->string('source_note')->nullable();
            $table->timestamps();

            $table->unique(['year', 'category']);
        });

        // 2025 qualifying cutoffs, consistently reported across sources (NTA result,
        // 14 June 2025). PwD sub-categories and 2024 (which had a disputed mid-year
        // revision) are intentionally left out of the seed — add via the admin screen
        // once verified from a primary source.
        DB::table('neet_category_cutoffs')->insert([
            [
                'year' => 2025,
                'category' => 'general',
                'percentile' => 50,
                'cutoff_marks_low' => 144,
                'cutoff_marks_high' => 686,
                'source_note' => 'NTA NEET (UG) 2025 result, 14 June 2025.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'year' => 2025,
                'category' => 'ews',
                'percentile' => 50,
                'cutoff_marks_low' => 144,
                'cutoff_marks_high' => 686,
                'source_note' => 'NTA NEET (UG) 2025 result, 14 June 2025.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'year' => 2025,
                'category' => 'obc_ncl',
                'percentile' => 40,
                'cutoff_marks_low' => 113,
                'cutoff_marks_high' => 143,
                'source_note' => 'NTA NEET (UG) 2025 result, 14 June 2025.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'year' => 2025,
                'category' => 'sc',
                'percentile' => 40,
                'cutoff_marks_low' => 113,
                'cutoff_marks_high' => 143,
                'source_note' => 'NTA NEET (UG) 2025 result, 14 June 2025.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'year' => 2025,
                'category' => 'st',
                'percentile' => 40,
                'cutoff_marks_low' => 113,
                'cutoff_marks_high' => 143,
                'source_note' => 'NTA NEET (UG) 2025 result, 14 June 2025.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('neet_category_cutoffs');
    }
};
