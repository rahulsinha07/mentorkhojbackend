<?php

namespace App\Http\Controllers\Api\V1\Mentor;

use App\CentralLogics\Helpers;
use App\CentralLogics\MentorEarningsLogic;
use App\CentralLogics\MentorImageService;
use App\CentralLogics\MentorLogic;
use App\CentralLogics\MentorWelcomeMailLogic;
use App\Http\Controllers\Controller;
use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorSetting;
use App\Model\Mentor\MentorShareLog;
use App\Model\Mentor\MentorShareTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MentorDashboardController extends Controller
{
    private function mentorForUser(Request $request): ?Mentor
    {
        return Mentor::where('user_id', $request->user()->id)->first();
    }

    public function show(Request $request): JsonResponse
    {
        $mentor = $this->mentorForUser($request);
        if (!$mentor) {
            return response()->json(['mentor' => null, 'has_profile' => false]);
        }

        $mentor->load(['services', 'settings']);
        $checklist = MentorLogic::setupChecklist($mentor);

        return response()->json([
            'has_profile' => true,
            'mentor' => array_merge(MentorLogic::formatPublic($mentor, true), [
                'status' => $mentor->status,
                'share_caption' => $mentor->share_caption,
            ]),
            'checklist' => $checklist,
            'checklist_progress' => MentorLogic::checklistProgress($checklist),
            'earnings_summary' => MentorEarningsLogic::summary($mentor),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (Mentor::where('user_id', $request->user()->id)->exists()) {
            return response()->json(['errors' => [['message' => 'Mentor profile already exists']]], 403);
        }

        $validator = Validator::make($request->all(), [
            'display_name' => 'required|string|max:255',
            'username' => 'nullable|string|max:100|regex:/^[a-z0-9-]+$/|unique:mentors,username',
            'headline' => 'nullable|string|max:500',
            'bio_html' => 'nullable|string',
            'category_ids' => 'required|array',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $username = $request->username
            ? MentorLogic::slugifyUsername($request->username)
            : MentorLogic::uniqueUsername($request->display_name);

        if (Mentor::where('username', $username)->exists()) {
            $username = MentorLogic::uniqueUsername($username);
        }

        $imageNames = MentorImageService::uploadMany($request->file('images') ?? []);
        if (empty($imageNames)) {
            $imageNames = ['default.png'];
        }

        $mentor = Mentor::create([
            'user_id' => $request->user()->id,
            'username' => $username,
            'display_name' => $request->display_name,
            'headline' => $request->headline,
            'bio_html' => $request->bio_html,
            'images' => json_encode($imageNames),
            'category_ids' => json_encode($request->category_ids),
            'status' => 'active',
            'is_published' => false,
        ]);

        MentorSetting::create(['mentor_id' => $mentor->id]);

        $user = $request->user();
        $welcomeEmailSent = false;

        try {
            $welcomeEmailSent = MentorWelcomeMailLogic::sendWelcomeEmail($mentor, $user);
        } catch (\Throwable $e) {
            Log::warning('Mentor welcome email error: ' . $e->getMessage(), [
                'mentor_id' => $mentor->id,
                'user_id' => $user->id,
            ]);
        }

        $mentor = $mentor->fresh(['services']);

        return response()->json([
            'message' => 'Mentor profile created',
            'mentor' => MentorLogic::formatPublic($mentor, true),
            'welcome_email_sent' => $welcomeEmailSent || (bool) ($mentor->welcome_email_sent_at ?? false),
        ], 201);
    }

    public function update(Request $request): JsonResponse
    {
        $mentor = $this->mentorForUser($request);
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $validator = Validator::make($request->all(), [
            'display_name' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|max:100|regex:/^[a-z0-9-]+$/|unique:mentors,username,' . $mentor->id,
            'headline' => 'nullable|string|max:500',
            'bio_html' => 'nullable|string',
            'category_ids' => 'sometimes|array',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'remove_images' => 'nullable|array',
            'social_links' => 'nullable|array',
            'social_links.instagram' => 'nullable|url|max:500',
            'social_links.facebook' => 'nullable|url|max:500',
            'social_links.linkedin' => 'nullable|url|max:500',
            'social_links.youtube' => 'nullable|url|max:500',
            'social_links.whatsapp' => 'nullable|url|max:500',
            'social_links.linktree' => 'nullable|url|max:500',
            'social_links.website' => 'nullable|url|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        if ($request->has('display_name')) {
            $mentor->display_name = $request->display_name;
        }
        if ($request->has('username')) {
            $mentor->username = MentorLogic::slugifyUsername($request->username);
        }
        if ($request->has('headline')) {
            $mentor->headline = $request->headline;
        }
        if ($request->has('bio_html')) {
            $mentor->bio_html = $request->bio_html;
        }
        if ($request->has('category_ids')) {
            $mentor->category_ids = json_encode($request->category_ids);
        }

        try {
            $updatedImages = MentorImageService::applyImageUpdate(
                $mentor,
                $request->input('remove_images', []),
                $request->file('images') ?? [],
            );
            if ($updatedImages !== null) {
                $mentor->images = $updatedImages;
            }
        } catch (\RuntimeException $e) {
            return response()->json(['errors' => [['message' => $e->getMessage()]]], 422);
        }
        if ($request->has('social_links')) {
            $mentor->social_links = json_encode(
                MentorLogic::normalizeSocialLinks($request->input('social_links', []))
            );
        }

        $mentor->save();

        return response()->json([
            'message' => 'Profile updated',
            'mentor' => MentorLogic::formatPublic($mentor->fresh('services'), true),
        ]);
    }

    public function publish(Request $request): JsonResponse
    {
        $mentor = $this->mentorForUser($request);
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $mentor->is_published = (bool) $request->input('is_published', true);
        if ($mentor->is_published) {
            $mentor->status = 'active';
        }
        $mentor->save();

        return response()->json([
            'message' => $mentor->is_published ? 'Page published' : 'Page unpublished',
            'is_published' => $mentor->is_published,
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $mentor = $this->mentorForUser($request);
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $settings = MentorSetting::firstOrCreate(['mentor_id' => $mentor->id]);

        if ($request->has('payout_details')) {
            $settings->payout_details = json_encode($request->payout_details);
        }
        if ($request->has('notification_prefs')) {
            $settings->notification_prefs = json_encode($request->notification_prefs);
        }
        if ($request->has('share_prefs')) {
            $settings->share_prefs = json_encode($request->share_prefs);
        }
        if ($request->has('availability_json')) {
            $settings->availability_json = json_encode($request->availability_json);
        }

        $settings->save();

        return response()->json(['message' => 'Settings updated', 'settings' => [
            'payout_details' => json_decode($settings->payout_details ?? '{}', true),
            'share_prefs' => json_decode($settings->share_prefs ?? '{}', true),
        ]]);
    }

    public function shareHub(Request $request): JsonResponse
    {
        $mentor = $this->mentorForUser($request);
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        return response()->json([
            'profile_url' => MentorLogic::profileUrl($mentor),
            'share_caption' => $mentor->share_caption,
            'has_shared' => MentorShareLog::where('mentor_id', $mentor->id)->exists(),
        ]);
    }

    public function shareTemplates(Request $request): JsonResponse
    {
        $templates = MentorShareTemplate::where('is_active', true)->orderBy('sort_order')->get();
        return response()->json(['templates' => $templates]);
    }

    public function composeShare(Request $request, string $templateSlug): JsonResponse
    {
        $mentor = $this->mentorForUser($request);
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $template = MentorShareTemplate::where('slug', $templateSlug)->where('is_active', true)->first();
        if (!$template) {
            return response()->json(['errors' => [['message' => 'Template not found']]], 404);
        }

        $channel = $request->query('channel', 'whatsapp');
        $profileUrl = MentorLogic::profileUrl($mentor);
        $caption = $template->default_caption ?? 'Book a session with me on MentorKhoj!';
        $caption = str_replace(
            ['{name}', '{url}', '{headline}'],
            [$mentor->display_name, $profileUrl, $mentor->headline ?? ''],
            $caption
        );

        $hashtags = $template->hashtags_array;
        $hashStr = implode(' ', array_map(fn ($h) => '#' . ltrim($h, '#'), $hashtags));
        $fullText = trim($caption . "\n\n" . $hashStr . "\n\n" . $profileUrl);

        $landingUrl = rtrim(config('app.mentorkhoj_site_url', 'https://www.mentorkhoj.com'), '/')
            . '/share/' . urlencode($templateSlug)
            . '?mentor=' . urlencode($mentor->username);

        $shareUrl = $channel === 'linkedin'
            ? 'https://www.linkedin.com/sharing/share-offsite/?url=' . urlencode($landingUrl)
            : 'https://wa.me/?text=' . urlencode($fullText);

        $posterPath = $templateSlug === 'profile-card'
            ? '/api/og/mentor-card/' . urlencode($mentor->username)
            : '/share-posters/' . ($template->poster_image ?: $templateSlug . '.png');
        $siteBase = rtrim(config('app.mentorkhoj_site_url', 'https://www.mentorkhoj.com'), '/');

        return response()->json([
            'profile_url' => $profileUrl,
            'caption' => $caption,
            'hashtags' => $hashtags,
            'full_text' => $fullText,
            'share_url' => $shareUrl,
            'channel' => $channel,
            'poster_preview_url' => $siteBase . $posterPath,
            'share_landing_url' => $landingUrl,
            'template' => ['slug' => $template->slug, 'title' => $template->title],
        ]);
    }

    public function logShare(Request $request): JsonResponse
    {
        $mentor = $this->mentorForUser($request);
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $validator = Validator::make($request->all(), [
            'channel' => 'required|in:linkedin,whatsapp,copy_link,download_poster',
            'template_id' => 'nullable|integer|exists:mentor_share_templates,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        MentorShareLog::create([
            'mentor_id' => $mentor->id,
            'template_id' => $request->template_id,
            'channel' => $request->channel,
            'profile_url' => MentorLogic::profileUrl($mentor),
        ]);

        return response()->json(['message' => 'Share logged']);
    }

    public function updateShareCaption(Request $request): JsonResponse
    {
        $mentor = $this->mentorForUser($request);
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $mentor->share_caption = $request->input('share_caption');
        $mentor->save();

        return response()->json(['message' => 'Share caption updated']);
    }
}
