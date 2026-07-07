<?php

namespace App\Console\Commands;

use App\CentralLogics\MentorLogic;
use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorService;
use App\Model\Mentor\MentorSetting;
use App\Model\Product;
use Illuminate\Console\Command;

class MigrateProductsToMentors extends Command
{
    protected $signature = 'mentors:migrate-from-products {--dry-run : Preview without writing}';

    protected $description = 'Copy legacy product-based mentors into mentors + mentor_services tables';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $products = Product::where('status', 1)->get();

        $migrated = 0;
        $skipped = 0;
        $servicesCreated = 0;
        $orphans = 0;

        foreach ($products as $product) {
            if (Mentor::where('legacy_product_id', $product->id)->exists()) {
                $skipped++;
                continue;
            }

            $username = MentorLogic::uniqueUsername($product->name ?? 'mentor-' . $product->id);

            $images = $product->image;
            if (is_string($images)) {
                $decoded = json_decode($images, true);
                $images = is_array($decoded) ? $decoded : [$images];
            }

            $categoryIds = $product->category_ids;
            if (is_string($categoryIds)) {
                $categoryIds = json_decode($categoryIds, true) ?? [];
            }

            if ($dryRun) {
                $this->line("Would migrate product #{$product->id} → username: {$username}");
                $migrated++;
                continue;
            }

            $mentor = Mentor::create([
                'user_id' => null,
                'legacy_product_id' => $product->id,
                'username' => $username,
                'display_name' => $product->name,
                'headline' => null,
                'bio_html' => $product->description,
                'images' => is_array($images) ? json_encode($images) : $product->image,
                'category_ids' => is_array($categoryIds) ? json_encode($categoryIds) : $product->category_ids,
                'status' => 'active',
                'is_published' => true,
                'profile_discount' => $product->discount ?? 0,
                'discount_type' => $product->discount_type ?? 'percent',
            ]);

            MentorSetting::create(['mentor_id' => $mentor->id]);
            $orphans++;

            $serviceCount = $this->migrateServices($mentor, $product);
            $servicesCreated += $serviceCount;
            $migrated++;
        }

        $this->info("Migrated: {$migrated}, Skipped: {$skipped}, Services: {$servicesCreated}, Orphans (no user_id): {$orphans}");

        return self::SUCCESS;
    }

    private function migrateServices(Mentor $mentor, Product $product): int
    {
        $count = 0;
        $variations = json_decode($product->variations ?? '[]', true) ?: [];
        $choiceOptions = json_decode($product->choice_options ?? '[]', true) ?: [];

        $optionLabels = [];
        if (!empty($choiceOptions[0]['options'])) {
            foreach ($choiceOptions[0]['options'] as $opt) {
                $optionLabels[] = is_array($opt) ? ($opt['label'] ?? $opt['name'] ?? 'Session') : (string) $opt;
            }
        }

        if (empty($variations)) {
            MentorService::create([
                'mentor_id' => $mentor->id,
                'title' => '1-on-1 Session',
                'duration_minutes' => 30,
                'price' => $product->price ?? 0,
                'is_enabled' => true,
                'sort_order' => 0,
            ]);
            return 1;
        }

        foreach ($variations as $i => $variation) {
            $type = $variation['type'] ?? ('type' . $i);
            $title = $optionLabels[$i] ?? ucfirst(str_replace('-', ' ', $type));
            $duration = (int) ($variation['stock'] ?? 30);
            $price = (float) ($variation['price'] ?? $product->price ?? 0);

            MentorService::create([
                'mentor_id' => $mentor->id,
                'title' => $title,
                'duration_minutes' => $duration > 0 ? $duration : 30,
                'price' => $price,
                'is_enabled' => true,
                'is_popular' => $i === 0,
                'sort_order' => $i,
            ]);
            $count++;
        }

        return $count;
    }
}
