<?php

namespace App\CentralLogics;

use App\Model\Category;
use App\Model\Mentor\Mentor;
use Illuminate\Support\Collection;

/**
 * Per-category browse ranks on mentors.category_ids JSON.
 * - default_rank: auto from bookings_count within a category cohort (most bookings = 1)
 * - rank: final display rank (seeded from default_rank; admin may override)
 * - position: membership order — never used as browse rank
 *
 * Only published mentors consume browse rank slots so draft mentors cannot
 * create gaps on the public /mentors?category=… list.
 */
class MentorCategoryRankLogic
{
    /** Featured parent category ids — mirrors Next FEATURED_CATEGORIES. */
    public const FEATURED_CATEGORY_IDS = [
        6, 25, 26, 59, 44, 29, 27, 28, 12, 8, 66, 70, 71, 9010, 9011,
    ];

    public static function featuredCategories(): Collection
    {
        return Category::query()
            ->whereIn('id', self::FEATURED_CATEGORY_IDS)
            ->get()
            ->sortBy(function ($cat) {
                $idx = array_search((int) $cat->id, self::FEATURED_CATEGORY_IDS, true);
                return $idx === false ? 999 : $idx;
            })
            ->values();
    }

    public static function isPublishedMentor(Mentor $mentor): bool
    {
        return (bool) ($mentor->is_published ?? false);
    }

    public static function mentorHasCategory(Mentor $mentor, int $categoryId): bool
    {
        foreach ($mentor->category_ids_array as $entry) {
            if ((int) ($entry['id'] ?? 0) === $categoryId) {
                return true;
            }
        }
        return false;
    }

    public static function entryRank(array $entry): ?int
    {
        if (!array_key_exists('rank', $entry) || $entry['rank'] === null || $entry['rank'] === '') {
            return null;
        }
        $n = (int) $entry['rank'];
        return $n > 0 ? $n : null;
    }

    public static function entryDefaultRank(array $entry): ?int
    {
        if (!array_key_exists('default_rank', $entry) || $entry['default_rank'] === null || $entry['default_rank'] === '') {
            return null;
        }
        $n = (int) $entry['default_rank'];
        return $n > 0 ? $n : null;
    }

    public static function rankForCategory(Mentor $mentor, int $categoryId): ?int
    {
        foreach ($mentor->category_ids_array as $entry) {
            if ((int) ($entry['id'] ?? 0) === $categoryId) {
                return self::entryRank($entry);
            }
        }
        return null;
    }

    public static function defaultRankForCategory(Mentor $mentor, int $categoryId): ?int
    {
        foreach ($mentor->category_ids_array as $entry) {
            if ((int) ($entry['id'] ?? 0) === $categoryId) {
                return self::entryDefaultRank($entry);
            }
        }
        return null;
    }

    /**
     * Assign default_rank by bookings_count DESC among published mentors in $categoryId.
     * Seeds final rank only when missing. Clears rank slots on unpublished mentors.
     * Densifies final ranks so public browse has contiguous 1..n.
     *
     * @param  Collection<int, Mentor>  $mentors
     */
    public static function syncDefaultsForCategory(Collection $mentors, int $categoryId): void
    {
        foreach ($mentors as $mentor) {
            if (!self::mentorHasCategory($mentor, $categoryId)) {
                continue;
            }
            if (!self::isPublishedMentor($mentor)) {
                self::clearRanksForCategory($mentor, $categoryId);
            }
        }

        $published = $mentors->filter(function (Mentor $mentor) use ($categoryId) {
            return self::isPublishedMentor($mentor) && self::mentorHasCategory($mentor, $categoryId);
        })->values();

        $sorted = $published->sort(function (Mentor $a, Mentor $b) {
            $bc = ((int) ($b->bookings_count ?? 0)) <=> ((int) ($a->bookings_count ?? 0));
            if ($bc !== 0) {
                return $bc;
            }
            return ((int) $a->id) <=> ((int) $b->id);
        })->values();

        $rank = 0;
        foreach ($sorted as $mentor) {
            $rank++;
            self::applyDefaultRankToMentor($mentor, $categoryId, $rank);
        }

        self::densifyPublishedRanks($published, $categoryId);
    }

    /**
     * Reassign contiguous final ranks among published mentors, preserving relative order.
     *
     * @param  Collection<int, Mentor>  $published
     */
    public static function densifyPublishedRanks(Collection $published, int $categoryId): void
    {
        $sorted = $published->sort(function (Mentor $a, Mentor $b) use ($categoryId) {
            $ra = self::rankForCategory($a, $categoryId) ?? PHP_INT_MAX;
            $rb = self::rankForCategory($b, $categoryId) ?? PHP_INT_MAX;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }
            $bc = ((int) ($b->bookings_count ?? 0)) <=> ((int) ($a->bookings_count ?? 0));
            if ($bc !== 0) {
                return $bc;
            }
            return ((int) $a->id) <=> ((int) $b->id);
        })->values();

        $n = 0;
        foreach ($sorted as $mentor) {
            $n++;
            $cats = $mentor->category_ids_array;
            $changed = false;
            foreach ($cats as $i => $entry) {
                if ((int) ($entry['id'] ?? 0) !== $categoryId) {
                    continue;
                }
                if ((int) ($entry['rank'] ?? 0) !== $n) {
                    $cats[$i]['rank'] = $n;
                    $changed = true;
                }
                break;
            }
            if ($changed) {
                $mentor->category_ids = json_encode(array_values($cats));
                $mentor->save();
            }
        }
    }

    public static function clearRanksForCategory(Mentor $mentor, int $categoryId): void
    {
        $cats = $mentor->category_ids_array;
        $changed = false;

        foreach ($cats as $i => $entry) {
            if ((int) ($entry['id'] ?? 0) !== $categoryId) {
                continue;
            }
            if (array_key_exists('rank', $entry) || array_key_exists('default_rank', $entry)) {
                unset($cats[$i]['rank'], $cats[$i]['default_rank']);
                $changed = true;
            }
            break;
        }

        if ($changed) {
            $mentor->category_ids = json_encode(array_values($cats));
            $mentor->save();
        }
    }

    public static function applyDefaultRankToMentor(Mentor $mentor, int $categoryId, int $defaultRank): void
    {
        $cats = $mentor->category_ids_array;
        $changed = false;

        foreach ($cats as $i => $entry) {
            if ((int) ($entry['id'] ?? 0) !== $categoryId) {
                continue;
            }
            $prevDefault = self::entryDefaultRank($entry);
            $prevRank = self::entryRank($entry);

            if ($prevDefault !== $defaultRank) {
                $cats[$i]['default_rank'] = $defaultRank;
                $changed = true;
            }
            if ($prevRank === null) {
                $cats[$i]['rank'] = $defaultRank;
                $changed = true;
            }
            break;
        }

        if ($changed) {
            $mentor->category_ids = json_encode(array_values($cats));
            $mentor->save();
            $mentor->unsetRelation('user');
        }
    }

    public static function updateFinalRank(Mentor $mentor, int $categoryId, ?int $rank): bool
    {
        $cats = $mentor->category_ids_array;
        $found = false;

        foreach ($cats as $i => $entry) {
            if ((int) ($entry['id'] ?? 0) !== $categoryId) {
                continue;
            }
            $found = true;
            if ($rank === null || $rank < 1) {
                unset($cats[$i]['rank']);
            } else {
                $cats[$i]['rank'] = $rank;
            }
            break;
        }

        if (!$found) {
            return false;
        }

        $mentor->category_ids = json_encode(array_values($cats));
        $mentor->save();
        return true;
    }

    /**
     * When rewriting category_ids from admin edit, keep rank/default_rank for matching ids.
     *
     * @param  array<int, array{id?: string|int, position?: int}>  $newEntries
     * @param  array<int, array<string, mixed>>  $existing
     * @return array<int, array<string, mixed>>
     */
    public static function mergeRankFields(array $newEntries, array $existing): array
    {
        $byId = [];
        foreach ($existing as $entry) {
            $id = (string) ($entry['id'] ?? '');
            if ($id !== '') {
                $byId[$id] = $entry;
            }
        }

        $out = [];
        foreach ($newEntries as $entry) {
            $id = (string) ($entry['id'] ?? '');
            $merged = $entry;
            if ($id !== '' && isset($byId[$id])) {
                if (array_key_exists('rank', $byId[$id])) {
                    $merged['rank'] = $byId[$id]['rank'];
                }
                if (array_key_exists('default_rank', $byId[$id])) {
                    $merged['default_rank'] = $byId[$id]['default_rank'];
                }
            }
            $out[] = $merged;
        }
        return $out;
    }

    public static function applyCategoryFilter($query, int $categoryId)
    {
        return $query->where(function ($q) use ($categoryId) {
            $q->where('category_ids', 'like', '%"id":' . $categoryId . '%')
                ->orWhere('category_ids', 'like', '%"id":"' . $categoryId . '"%');
        });
    }
}
