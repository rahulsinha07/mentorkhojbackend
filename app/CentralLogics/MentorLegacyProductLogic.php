<?php

namespace App\CentralLogics;

use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MentorLegacyProductLogic
{
    /**
     * Ensure mentor has a linked legacy products row for online checkout.
     * Creates one from enabled services when missing.
     */
    public static function ensureForMentor(Mentor $mentor): ?int
    {
        $mentor->loadMissing('enabledServices');

        if ($mentor->legacy_product_id) {
            self::syncProductFromServices($mentor);

            return (int) $mentor->legacy_product_id;
        }

        if ($mentor->enabledServices->isEmpty()) {
            return null;
        }

        try {
            return DB::transaction(function () use ($mentor) {
                $mentor->refresh();
                if ($mentor->legacy_product_id) {
                    return (int) $mentor->legacy_product_id;
                }

                $productId = DB::table('products')->insertGetId(
                    self::buildProductPayload($mentor, $mentor->enabledServices)
                );
                $mentor->update(['legacy_product_id' => $productId]);

                return $productId;
            });
        } catch (\Throwable $e) {
            Log::warning('Mentor legacy product ensure failed', [
                'mentor_id' => $mentor->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public static function isOnlinePayReady(Mentor $mentor): bool
    {
        return (bool) ($mentor->legacy_product_id ?: self::ensureForMentor($mentor));
    }

    /**
     * Keep linked product variations/pricing aligned with mentor services.
     */
    public static function syncProductFromServices(Mentor $mentor): void
    {
        if (!$mentor->legacy_product_id) {
            return;
        }

        $mentor->loadMissing('enabledServices');
        if ($mentor->enabledServices->isEmpty()) {
            return;
        }

        $payload = self::buildProductPayload($mentor, $mentor->enabledServices);
        DB::table('products')->where('id', $mentor->legacy_product_id)->update([
            'name' => $payload['name'],
            'description' => $payload['description'],
            'image' => $payload['image'],
            'price' => $payload['price'],
            'variations' => $payload['variations'],
            'choice_options' => $payload['choice_options'],
            'discount' => $payload['discount'],
            'discount_type' => $payload['discount_type'],
            'capacity' => $payload['capacity'],
            'updated_at' => now(),
        ]);
    }

    /** @param Collection<int, MentorService> $services */
    private static function buildProductPayload(Mentor $mentor, Collection $services): array
    {
        $first = $services->first();
        $variations = [];
        $options = [];

        foreach ($services->values() as $i => $service) {
            $type = preg_replace('/\s+/', '', $service->title) ?: ('Session' . $i);
            $variations[] = [
                'type' => $type,
                'price' => (float) $service->price,
                'stock' => (int) ($service->duration_minutes ?: 30),
            ];
            $options[] = $service->title;
        }

        $categoryIds = $mentor->category_ids_array ?? [];
        $categoryPayload = [];
        foreach (array_values($categoryIds) as $pos => $entry) {
            if (is_array($entry) && isset($entry['id'])) {
                $categoryPayload[] = [
                    'id' => (string) $entry['id'],
                    'position' => (string) ($entry['position'] ?? $pos),
                ];
                continue;
            }
            $categoryPayload[] = ['id' => (string) $entry, 'position' => (string) $pos];
        }
        if ($categoryPayload === []) {
            $categoryPayload[] = ['id' => '6', 'position' => '0'];
        }

        $images = $mentor->images_array ?? [];
        if ($images === []) {
            $images = ['def.png'];
        }

        return [
            'name' => $mentor->display_name,
            'description' => strip_tags((string) ($mentor->bio_html ?? $mentor->headline ?? '')),
            'image' => json_encode(array_values($images)),
            'price' => (float) $first->price,
            'variations' => json_encode($variations),
            'tax' => 0,
            'status' => 1,
            'attributes' => json_encode([]),
            'category_ids' => json_encode($categoryPayload),
            'choice_options' => json_encode($options ? [['name' => 'choice_0', 'title' => 'Session type', 'options' => $options]] : []),
            'discount' => (float) ($mentor->profile_discount ?? 0),
            'discount_type' => $mentor->discount_type ?? 'percent',
            'tax_type' => 'percent',
            'unit' => 'Minute',
            'total_stock' => 999,
            'capacity' => (float) ($first->duration_minutes ?: 30),
            'daily_needs' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
