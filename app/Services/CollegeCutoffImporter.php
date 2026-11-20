<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class CollegeCutoffImporter
{
    private const EXAM_TYPE = 'jee_main';

    /** Expected header, matching the JoSAA opening/closing rank archive's own column order. */
    private const EXPECTED_HEADER = [
        'institute', 'program', 'quota', 'seat_type', 'gender', 'opening_rank', 'closing_rank',
    ];

    /**
     * @return array{imported: int, skipped: int}
     */
    public static function importFromCsv(string $path, int $year, int $round): array
    {
        $handle = fopen($path, 'r');
        if (!$handle) {
            throw new RuntimeException("Could not open CSV at {$path}");
        }

        $header = fgetcsv($handle);
        $normalizedHeader = array_map(fn ($h) => strtolower(trim((string) $h)), $header ?: []);

        if ($normalizedHeader !== self::EXPECTED_HEADER) {
            fclose($handle);
            throw new RuntimeException('CSV header must be exactly: ' . implode(', ', self::EXPECTED_HEADER));
        }

        $rows = [];
        $skipped = 0;
        $now = now();

        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) !== 7) {
                $skipped++;
                continue;
            }

            [$institute, $program, $quota, $seatType, $genderRaw, $openingRaw, $closingRaw] = $line;

            $institute = trim($institute);
            if ($institute === '') {
                $skipped++;
                continue;
            }

            $isPwd = str_contains($seatType, 'PwD');
            $category = strtolower(str_replace(['-', ' (pwd)', ' '], ['_', '', ''], strtolower(trim($seatType))));
            $category = str_replace('(pwd)', '', $category);
            $category = trim($category, '_');

            $gender = str_starts_with(trim($genderRaw), 'Female') ? 'female' : 'gender_neutral';

            $isPreparatory = str_ends_with(trim($openingRaw), 'P') || str_ends_with(trim($closingRaw), 'P');

            $rows[] = [
                'exam_type' => self::EXAM_TYPE,
                'year' => $year,
                'round' => $round,
                'institute_type' => self::classifyInstituteType($institute),
                'institute_name' => $institute,
                'program' => trim($program),
                'quota' => trim($quota),
                'category' => $category,
                'is_pwd' => $isPwd,
                'gender' => $gender,
                'opening_rank' => self::parseRank($openingRaw),
                'closing_rank' => self::parseRank($closingRaw),
                'is_preparatory' => $isPreparatory,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        fclose($handle);

        if (empty($rows)) {
            return ['imported' => 0, 'skipped' => $skipped];
        }

        DB::transaction(function () use ($rows, $year, $round) {
            DB::table('college_cutoffs')
                ->where('exam_type', self::EXAM_TYPE)
                ->where('year', $year)
                ->where('round', $round)
                ->delete();

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('college_cutoffs')->insert($chunk);
            }
        });

        return ['imported' => count($rows), 'skipped' => $skipped];
    }

    private static function parseRank(string $raw): ?int
    {
        $digits = preg_replace('/[^0-9]/', '', $raw);
        return $digits === '' ? null : (int) $digits;
    }

    private static function classifyInstituteType(string $name): string
    {
        $n = strtolower($name);

        if (str_contains($n, 'engineering science and technology')) return 'IIEST';
        if (str_contains($n, 'information technology')) return 'IIIT';
        if (str_contains($n, 'indian institute of technology')) return 'IIT';
        if (str_contains($n, 'national institute of technology')) return 'NIT';

        return 'GFTI';
    }
}
