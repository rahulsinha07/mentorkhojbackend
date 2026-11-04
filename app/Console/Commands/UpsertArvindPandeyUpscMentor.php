<?php

namespace App\Console\Commands;

use App\CentralLogics\MentorKhojRevalidateLogic;
use App\CentralLogics\MentorLegacyProductLogic;
use App\CentralLogics\MentorLogic;
use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorService;
use App\Model\Mentor\MentorSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class UpsertArvindPandeyUpscMentor extends Command
{
    protected $signature = 'mentors:upsert-arvind-pandey-upsc';

    protected $description = 'Idempotent upsert of Arvind Pandey IPS as a free UPSC mentor on MentorKhoj';

    private const USERNAME = 'arvind-pandey-ips';
    private const UPSC_CATEGORY_ID = '25';
    private const PORTRAIT_FILENAME = 'arvind-pandey-ips-portrait.jpg';
    private const SERVICE_TITLE = 'UPSC Civil Services Strategy';

    public function handle(): int
    {
        $mentor = Mentor::firstOrNew(['username' => self::USERNAME]);

        $mentor->display_name = 'Arvind Pandey IPS';
        $mentor->headline = 'UPSC Mentor · Retired IPS (1988 Bihar cadre) · Former DGP Bihar';
        $mentor->bio_html = $this->bioHtml();
        $mentor->category_ids = json_encode([
            ['id' => self::UPSC_CATEGORY_ID, 'position' => 1],
        ]);
        $mentor->status = 'active';
        $mentor->is_published = true;
        $mentor->share_caption = 'Book a free 1-on-1 UPSC strategy session with Arvind Pandey IPS on MentorKhoj.';
        $mentor->social_links = json_encode(MentorLogic::normalizeSocialLinks([
            'instagram' => 'https://www.instagram.com/arvindpandeybihar',
            'facebook' => 'https://www.facebook.com/ArvindPandey.IPS',
            'linkedin' => 'https://in.linkedin.com/in/ipsarvindpandey',
            'youtube' => 'https://www.youtube.com/c/ArvindPandeyIPS',
            'website' => 'https://creativepolicing.yoga',
            'threads' => 'https://www.threads.com/@arvindpandeybihar',
        ]));

        $imageName = $this->ensurePortrait();
        if ($imageName) {
            $mentor->images = json_encode([$imageName]);
        }

        $mentor->save();

        MentorSetting::firstOrCreate(['mentor_id' => $mentor->id]);

        $this->syncFreeService($mentor);
        MentorLegacyProductLogic::ensureForMentor($mentor->fresh('enabledServices'));

        MentorKhojRevalidateLogic::revalidatePaths([
            '/mentor/' . self::USERNAME,
            '/mentors',
        ]);

        $this->info('Upserted mentor #' . $mentor->id . ' @' . self::USERNAME);
        $this->line(MentorLogic::profileUrl($mentor));

        return self::SUCCESS;
    }

    private function ensurePortrait(): ?string
    {
        $dest = 'product/' . self::PORTRAIT_FILENAME;
        if (Storage::disk('public')->exists($dest)) {
            return self::PORTRAIT_FILENAME;
        }

        $candidates = [
            public_path('assets/arvind/arvind-pandey-ips-bihar-portrait.jpg'),
            base_path('public/assets/arvind/arvind-pandey-ips-bihar-portrait.jpg'),
        ];
        foreach ($candidates as $src) {
            if (!is_file($src)) {
                continue;
            }
            $bytes = file_get_contents($src);
            if ($bytes === false) {
                continue;
            }
            Storage::disk('public')->put($dest, $bytes);
            if (Storage::disk('public')->exists($dest)) {
                return self::PORTRAIT_FILENAME;
            }
        }

        $this->warn('Portrait source not found; leaving existing images.');

        return null;
    }

    private function syncFreeService(Mentor $mentor): void
    {
        $service = MentorService::query()
            ->where('mentor_id', $mentor->id)
            ->where('title', self::SERVICE_TITLE)
            ->first();

        if (!$service) {
            $service = new MentorService([
                'mentor_id' => $mentor->id,
                'title' => self::SERVICE_TITLE,
            ]);
        }

        $service->description = 'Free 60-minute 1-on-1 UPSC Civil Services strategy session: prelims–mains–interview roadmap, ethics, and officer-minded preparation. No payment.';
        $service->duration_minutes = 60;
        $service->price = 0;
        $service->compare_at_price = 0;
        $service->badge = 'FREE';
        $service->is_enabled = true;
        $service->is_popular = true;
        $service->sort_order = 1;
        $service->meeting_type = 'video';
        $service->save();

        MentorService::query()
            ->where('mentor_id', $mentor->id)
            ->where('id', '!=', $service->id)
            ->update(['is_enabled' => false]);
    }

    private function bioHtml(): string
    {
        return <<<'HTML'
<p><strong>Arvind Pandey IPS</strong> is a retired 1988-batch Bihar cadre Indian Police Service officer and former DGP cum Civil Defence Commissioner of Bihar (January 2019–June 2023). On MentorKhoj he offers a <strong>free UPSC Civil Services strategy session</strong> for aspirants who want officer-level guidance — not a recycled coaching-class lecture.</p>
<p>Four decades in the IPS shape how he mentors: how to think under pressure, how to write with clarity, and how to carry yourself in the Personality Test. Prelims, mains, ethics (GS-IV), and interview temperament are the focus. His Civil Services optionals were Sanskrit and Philosophy — useful for aspirants choosing humanities optionals and for anyone who needs a steadier inner discipline.</p>
<p>This MentorKhoj page is for <strong>booking a free 1-on-1 UPSC session</strong>. It is not a second biography. Full career, Creative Policing, and official personal-brand material live on his hub.</p>
<h3>Related official pages</h3>
<ul>
<li>Official biography — Creative Policing Yoga: <a href="https://creativepolicing.yoga/hi/arvind-pandey-ips-bihar">creativepolicing.yoga/hi/arvind-pandey-ips-bihar</a></li>
<li>Health / yoga profile: <a href="https://aihealtheval.com/arvind-pandey-ips-bihar">aihealtheval.com/arvind-pandey-ips-bihar</a></li>
</ul>
<p>Book the free UPSC Civil Services Strategy session on this page. No fee, no paid add-on.</p>
HTML;
    }
}
