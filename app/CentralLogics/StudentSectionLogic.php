<?php

namespace App\CentralLogics;

use App\Model\DemoBooking;
use App\Model\Mentor\MentorBooking;
use App\Model\StudentSectionOverride;
use App\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StudentSectionLogic
{
    public const SECTIONS = [
        'neet' => 'NEET',
        'jee' => 'IIT-JEE',
        'tech' => 'Tech',
        'ai' => 'AI/ML',
        'other' => 'Other',
    ];

    public const SETTINGS_KEY = 'student_section_whatsapp';

    public const DEFAULT_TEMPLATES = [
        'neet' => "Hi {name},\n\nThis is MentorKhoj. We're reaching out about NEET mentorship — happy to help you find the right mentor and plan next steps.\n\nReply here anytime.",
        'jee' => "Hi {name},\n\nThis is MentorKhoj. We're reaching out about IIT-JEE mentorship — happy to help you find the right mentor and plan next steps.\n\nReply here anytime.",
        'tech' => "Hi {name},\n\nThis is MentorKhoj. We're reaching out about Tech mentorship — happy to help you find the right mentor and plan next steps.\n\nReply here anytime.",
        'ai' => "Hi {name},\n\nThis is MentorKhoj. We're reaching out about AI/ML mentorship — happy to help you find the right mentor and plan next steps.\n\nReply here anytime.",
        'other' => "Hi {name},\n\nThis is MentorKhoj. Welcome! We can help you find the right mentor and book a session.\n\nReply here anytime.",
    ];

    /** Exact / alias slugs → section key. */
    private const SECTION_ALIASES = [
        'neet' => 'neet',
        'jee' => 'jee',
        'iit' => 'jee',
        'iit-jee' => 'jee',
        'iitjee' => 'jee',
        'tech' => 'tech',
        'sde' => 'tech',
        'ai' => 'ai',
        'ml' => 'ai',
        'ai-ml' => 'ai',
        'aiml' => 'ai',
        'genai' => 'ai',
    ];

    /** Non-exam / school / other careers → Other (never NEET). */
    private const OTHER_SLUGS = [
        'class-10',
        'class-11-12',
        'class10',
        'class11',
        'class12',
        'school',
        'upsc',
        'ca',
        'clat',
        'lawyer',
        'law',
        'bank-jobs',
        'bank',
        'biology',
        'civil-engineer',
        'civil',
    ];

    /**
     * Map demo vertical/category/label to a Students-by-Section tab.
     * Prefer explicit vertical; never default unknown rows to NEET.
     */
    public static function resolveSection(?string $vertical, ?string $category = null, ?string $categoryLabel = null): string
    {
        $verticalNorm = self::normalizeSlug($vertical);
        $categoryNorm = self::normalizeSlug($category);
        $labelNorm = strtolower(trim((string) $categoryLabel));

        // 1) Explicit vertical wins (fixes vertical=ai + category=tech).
        if ($verticalNorm !== '' && isset(self::SECTION_ALIASES[$verticalNorm])) {
            return self::SECTION_ALIASES[$verticalNorm];
        }

        // 2) Exact category slug.
        if ($categoryNorm !== '' && isset(self::SECTION_ALIASES[$categoryNorm])) {
            return self::SECTION_ALIASES[$categoryNorm];
        }

        // 3) Known non-exam → other.
        if ($verticalNorm !== '' && in_array($verticalNorm, self::OTHER_SLUGS, true)) {
            return 'other';
        }
        if ($categoryNorm !== '' && in_array($categoryNorm, self::OTHER_SLUGS, true)) {
            return 'other';
        }

        // 4) Token / label scan (prefer specific exam names; avoid bare "ai" inside random words).
        $hay = strtolower(trim(implode(' ', array_filter([
            (string) $vertical,
            (string) $category,
            (string) $categoryLabel,
        ]))));

        if ($hay !== '') {
            if (preg_match('/\bneet\b/i', $hay) || str_contains($labelNorm, 'neet')) {
                return 'neet';
            }
            if (preg_match('/\b(iit[\s_-]?jee|jee|iit)\b/i', $hay) || str_contains($labelNorm, 'jee') || str_contains($labelNorm, 'iit')) {
                return 'jee';
            }
            // AI/ML labels before generic "tech" so "AI / ML" with category tech stays AI.
            if (
                preg_match('/\b(ai[\s_\/-]*ml|genai|machine\s*learning)\b/i', $hay)
                || preg_match('/\bai\b/i', $labelNorm)
                || str_contains($labelNorm, 'ai / ml')
                || str_contains($labelNorm, 'ai/ml')
                || $labelNorm === 'ai'
            ) {
                return 'ai';
            }
            if (preg_match('/\b(tech|sde)\b/i', $hay) || str_contains($labelNorm, 'tech career')) {
                return 'tech';
            }
            if (preg_match('/\b(school|class[\s_-]?1[0-2]|grade\s*1[0-2]|upsc|clat|bank)\b/i', $hay)) {
                return 'other';
            }
        }

        // 5) Never default to neet.
        return 'other';
    }

    private static function normalizeSlug(?string $value): string
    {
        $v = strtolower(trim((string) $value));
        if ($v === '') {
            return '';
        }

        return preg_replace('/[^a-z0-9]+/', '-', $v) ?? $v;
    }

    /**
     * @return array{students: Collection, counts: array<string,int>, total: int}
     */
    public static function listStudents(?string $section = null, ?string $q = null, ?string $source = null): array
    {
        $all = self::aggregateStudents();
        $counts = self::countBySection($all);
        $students = $all;

        if ($section && isset(self::SECTIONS[$section])) {
            $students = $students->filter(static fn ($row) => ($row['section'] ?? 'other') === $section)->values();
        }

        if ($source && in_array($source, ['demo', 'mentor', 'both'], true)) {
            $students = $students->filter(static function ($row) use ($source) {
                $hasDemo = !empty($row['has_demo']);
                $hasMentor = !empty($row['has_mentor']);
                if ($source === 'demo') {
                    return $hasDemo && !$hasMentor;
                }
                if ($source === 'mentor') {
                    return $hasMentor && !$hasDemo;
                }

                return $hasDemo && $hasMentor;
            })->values();
        }

        $q = trim((string) $q);
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $students = $students->filter(static function ($row) use ($needle) {
                $hay = mb_strtolower(implode(' ', [
                    $row['name'] ?? '',
                    $row['phone'] ?? '',
                    $row['email'] ?? '',
                    $row['phone_digits'] ?? '',
                ]));

                return str_contains($hay, $needle);
            })->values();
        }

        return [
            'students' => $students,
            'counts' => $counts,
            'total' => array_sum($counts),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function aggregateStudents(): Collection
    {
        /** @var array<string, array<string, mixed>> $byKey */
        $byKey = [];

        $demos = DemoBooking::query()
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'phone', 'email', 'category', 'category_label', 'vertical', 'user_id', 'created_at', 'booking_ref']);

        foreach ($demos as $demo) {
            $digits = WhatsAppWebLink::digits((string) $demo->phone);
            $email = self::normalizeEmail($demo->email);
            $key = self::rowKey($digits, $email, $demo->user_id ? (int) $demo->user_id : null);
            if ($key === null) {
                continue;
            }

            $inferred = self::resolveSection(
                $demo->vertical ? (string) $demo->vertical : null,
                $demo->category ? (string) $demo->category : null,
                $demo->category_label ? (string) $demo->category_label : null
            );
            if (!isset(self::SECTIONS[$inferred])) {
                $inferred = 'other';
            }

            $created = $demo->created_at ? $demo->created_at->copy() : null;
            self::mergeRow($byKey, $key, [
                'name' => trim((string) $demo->name),
                'phone' => trim((string) $demo->phone),
                'phone_digits' => $digits,
                'email' => $email,
                'user_id' => $demo->user_id ? (int) $demo->user_id : null,
                'inferred_section' => $inferred,
                'has_demo' => true,
                'has_mentor' => false,
                'latest_at' => $created,
                'demo_ref' => (string) $demo->booking_ref,
                'demo_id' => (int) $demo->id,
            ]);
        }

        $menteeIds = MentorBooking::query()
            ->whereNotNull('mentee_user_id')
            ->distinct()
            ->pluck('mentee_user_id')
            ->map(static fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        if ($menteeIds !== []) {
            $latestBookingAt = MentorBooking::query()
                ->select('mentee_user_id', DB::raw('MAX(created_at) as latest_at'))
                ->whereIn('mentee_user_id', $menteeIds)
                ->groupBy('mentee_user_id')
                ->pluck('latest_at', 'mentee_user_id');

            $users = User::query()
                ->whereIn('id', $menteeIds)
                ->get(['id', 'f_name', 'l_name', 'phone', 'email']);

            foreach ($users as $user) {
                $name = trim(implode(' ', array_filter([(string) $user->f_name, (string) $user->l_name])));
                if ($name === '') {
                    $name = trim((string) ($user->name ?? ''));
                }
                $digits = WhatsAppWebLink::digits((string) $user->phone);
                $email = self::normalizeEmail($user->email);
                $key = self::rowKey($digits, $email, (int) $user->id);
                if ($key === null) {
                    continue;
                }

                $latestRaw = $latestBookingAt[$user->id] ?? null;
                $created = $latestRaw ? \Carbon\Carbon::parse($latestRaw) : null;

                self::mergeRow($byKey, $key, [
                    'name' => $name,
                    'phone' => trim((string) $user->phone),
                    'phone_digits' => $digits,
                    'email' => $email,
                    'user_id' => (int) $user->id,
                    'inferred_section' => 'other',
                    'has_demo' => false,
                    'has_mentor' => true,
                    'latest_at' => $created,
                    'demo_ref' => null,
                    'demo_id' => null,
                ]);
            }
        }

        $overrides = self::loadOverridesIndexed();
        $openWindows = self::openWindowWaIds(
            collect(array_values($byKey))->pluck('phone_digits')->filter()->unique()->values()->all()
        );

        $rows = collect(array_values($byKey))->map(function (array $row) use ($overrides, $openWindows) {
            $override = self::findOverride($overrides, $row);
            $section = $override['section'] ?? ($row['inferred_section'] ?? 'other');
            if (!isset(self::SECTIONS[$section])) {
                $section = 'other';
            }

            $row['section'] = $section;
            $row['section_label'] = self::SECTIONS[$section];
            $row['override_id'] = $override['id'] ?? null;
            $row['draft_message'] = $override['message_template'] ?? null;
            $row['source_label'] = self::sourceLabel(!empty($row['has_demo']), !empty($row['has_mentor']));
            $row['message'] = self::renderMessage(
                $section,
                (string) ($row['name'] ?? ''),
                $row['draft_message']
            );
            $row['wa_url'] = WhatsAppWebLink::url(
                (string) ($row['phone'] ?? ''),
                (string) $row['message']
            );
            $digits = (string) ($row['phone_digits'] ?? '');
            $row['window_open'] = $digits !== '' && isset($openWindows[$digits]);

            return $row;
        })->sortByDesc(static function ($row) {
            $at = $row['latest_at'] ?? null;

            return $at ? $at->timestamp : 0;
        })->values();

        return $rows;
    }

    /**
     * Stable section order for "All" grouped view: NEET, IIT-JEE, Tech, AI, Other.
     *
     * @param Collection<int, array<string, mixed>> $students
     * @return Collection<int, array<string, mixed>>
     */
    public static function sortGroupedBySection(Collection $students): Collection
    {
        $order = array_flip(array_keys(self::SECTIONS));

        return $students->sort(static function ($a, $b) use ($order) {
            $sa = $order[$a['section'] ?? 'other'] ?? 99;
            $sb = $order[$b['section'] ?? 'other'] ?? 99;
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }
            $ta = isset($a['latest_at']) && $a['latest_at'] ? $a['latest_at']->timestamp : 0;
            $tb = isset($b['latest_at']) && $b['latest_at'] ? $b['latest_at']->timestamp : 0;

            return $tb <=> $ta;
        })->values();
    }

    /**
     * @param list<string> $waIds
     * @return array<string, true>
     */
    private static function openWindowWaIds(array $waIds): array
    {
        if ($waIds === [] || !Schema::hasTable('whatsapp_messages')) {
            return [];
        }

        $since = now()->subHours(24);
        $open = \App\Model\WhatsApp\WhatsAppMessage::query()
            ->whereIn('wa_id', $waIds)
            ->where('direction', 'in')
            ->where('occurred_at', '>=', $since)
            ->distinct()
            ->pluck('wa_id')
            ->all();

        $map = [];
        foreach ($open as $id) {
            $map[(string) $id] = true;
        }

        return $map;
    }

    /**
     * @param Collection<int, array<string, mixed>> $students
     * @return array<string, int>
     */
    public static function countBySection(Collection $students): array
    {
        $counts = array_fill_keys(array_keys(self::SECTIONS), 0);
        foreach ($students as $row) {
            $key = $row['section'] ?? 'other';
            if (!isset($counts[$key])) {
                $key = 'other';
            }
            $counts[$key]++;
        }

        return $counts;
    }

    public static function loadTemplates(): array
    {
        $defaults = self::DEFAULT_TEMPLATES;
        $row = DB::table('business_settings')->where('key', self::SETTINGS_KEY)->first();
        if (!$row || !$row->value) {
            return $defaults;
        }
        $decoded = json_decode($row->value, true);
        if (!is_array($decoded)) {
            return $defaults;
        }
        $templates = $decoded['templates'] ?? $decoded;
        if (!is_array($templates)) {
            return $defaults;
        }

        $out = $defaults;
        foreach (array_keys(self::SECTIONS) as $key) {
            $val = trim((string) ($templates[$key] ?? ''));
            if ($val !== '') {
                $out[$key] = $val;
            }
        }

        return $out;
    }

    public static function saveTemplates(array $templates): void
    {
        $clean = [];
        foreach (array_keys(self::SECTIONS) as $key) {
            $clean[$key] = trim((string) ($templates[$key] ?? self::DEFAULT_TEMPLATES[$key]));
            if ($clean[$key] === '') {
                $clean[$key] = self::DEFAULT_TEMPLATES[$key];
            }
        }

        $payload = json_encode(['templates' => $clean], JSON_UNESCAPED_UNICODE);
        $existing = DB::table('business_settings')->where('key', self::SETTINGS_KEY)->first();
        if ($existing) {
            DB::table('business_settings')->where('id', $existing->id)->update([
                'value' => $payload,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('business_settings')->insert([
                'key' => self::SETTINGS_KEY,
                'value' => $payload,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public static function renderMessage(string $section, string $name, ?string $draft = null): string
    {
        if ($draft !== null && trim($draft) !== '') {
            $text = $draft;
        } else {
            $templates = self::loadTemplates();
            $text = $templates[$section] ?? self::DEFAULT_TEMPLATES['other'];
        }

        $label = self::SECTIONS[$section] ?? 'mentorship';
        $greeting = trim($name) !== '' ? trim($name) : 'there';

        return str_replace(
            ['{name}', '{section}'],
            [$greeting, $label],
            $text
        );
    }

    /**
     * Upsert section override keyed by phone digits (preferred) or email/user_id.
     */
    public static function upsertOverride(
        string $section,
        ?string $phone = null,
        ?string $email = null,
        ?int $userId = null,
        ?string $messageTemplate = null,
        bool $updateMessage = false
    ): ?StudentSectionOverride {
        if (!Schema::hasTable('student_section_overrides')) {
            return null;
        }

        if (!isset(self::SECTIONS[$section])) {
            $section = 'other';
        }

        $digits = WhatsAppWebLink::digits((string) $phone);
        $emailNorm = self::normalizeEmail($email);

        $query = StudentSectionOverride::query();
        if ($digits) {
            $row = $query->where('phone_digits', $digits)->first();
        } elseif ($userId) {
            $row = $query->where('user_id', $userId)->first();
        } elseif ($emailNorm) {
            $row = $query->where('email', $emailNorm)->first();
        } else {
            return null;
        }

        if (!$row) {
            $row = new StudentSectionOverride();
            $row->phone_digits = $digits;
            $row->email = $emailNorm;
            $row->user_id = $userId;
        } else {
            if ($digits) {
                $row->phone_digits = $digits;
            }
            if ($emailNorm) {
                $row->email = $emailNorm;
            }
            if ($userId) {
                $row->user_id = $userId;
            }
        }

        $row->section = $section;
        if ($updateMessage) {
            $row->message_template = $messageTemplate !== null && trim($messageTemplate) !== ''
                ? trim($messageTemplate)
                : null;
        }
        $row->save();

        return $row;
    }

    private static function normalizeEmail($email): ?string
    {
        $email = strtolower(trim((string) $email));

        return $email !== '' ? $email : null;
    }

    private static function rowKey(?string $digits, ?string $email, ?int $userId): ?string
    {
        if ($digits) {
            return 'p:'.$digits;
        }
        if ($email) {
            return 'e:'.$email;
        }
        if ($userId) {
            return 'u:'.$userId;
        }

        return null;
    }

    /**
     * @param array<string, array<string, mixed>> $byKey
     * @param array<string, mixed> $incoming
     */
    private static function mergeRow(array &$byKey, string $key, array $incoming): void
    {
        // Also try to merge across alternate keys (phone ↔ email ↔ user) when one side is empty.
        $existingKey = $key;
        if (!isset($byKey[$key])) {
            foreach ($byKey as $k => $existing) {
                if (self::rowsMatch($existing, $incoming)) {
                    $existingKey = $k;
                    break;
                }
            }
        }

        if (!isset($byKey[$existingKey])) {
            $byKey[$existingKey] = $incoming;

            return;
        }

        $cur = &$byKey[$existingKey];
        $cur['has_demo'] = !empty($cur['has_demo']) || !empty($incoming['has_demo']);
        $cur['has_mentor'] = !empty($cur['has_mentor']) || !empty($incoming['has_mentor']);

        $inAt = $incoming['latest_at'] ?? null;
        $curAt = $cur['latest_at'] ?? null;
        $incomingNewer = $inAt && (!$curAt || $inAt->gt($curAt));

        if ($incomingNewer) {
            $cur['latest_at'] = $inAt;
            if (trim((string) ($incoming['name'] ?? '')) !== '') {
                $cur['name'] = $incoming['name'];
            }
        } elseif (trim((string) ($cur['name'] ?? '')) === '' && trim((string) ($incoming['name'] ?? '')) !== '') {
            $cur['name'] = $incoming['name'];
        }

        if (empty($cur['phone']) && !empty($incoming['phone'])) {
            $cur['phone'] = $incoming['phone'];
            $cur['phone_digits'] = $incoming['phone_digits'] ?? null;
        }
        if (empty($cur['email']) && !empty($incoming['email'])) {
            $cur['email'] = $incoming['email'];
        }
        if (empty($cur['user_id']) && !empty($incoming['user_id'])) {
            $cur['user_id'] = $incoming['user_id'];
        }

        // Prefer a real vertical from demos over mentor-only "other".
        $curInf = $cur['inferred_section'] ?? 'other';
        $inInf = $incoming['inferred_section'] ?? 'other';
        if ($curInf === 'other' && $inInf !== 'other') {
            $cur['inferred_section'] = $inInf;
        } elseif (!empty($incoming['has_demo']) && $inInf !== 'other') {
            $cur['inferred_section'] = $inInf;
        }

        if (empty($cur['demo_ref']) && !empty($incoming['demo_ref'])) {
            $cur['demo_ref'] = $incoming['demo_ref'];
            $cur['demo_id'] = $incoming['demo_id'] ?? null;
        }

        // Re-key to phone when we now have digits.
        $preferred = self::rowKey(
            $cur['phone_digits'] ?? null,
            $cur['email'] ?? null,
            $cur['user_id'] ?? null
        );
        if ($preferred && $preferred !== $existingKey && !isset($byKey[$preferred])) {
            $byKey[$preferred] = $cur;
            unset($byKey[$existingKey]);
        }
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    private static function rowsMatch(array $a, array $b): bool
    {
        if (!empty($a['phone_digits']) && !empty($b['phone_digits']) && $a['phone_digits'] === $b['phone_digits']) {
            return true;
        }
        if (!empty($a['user_id']) && !empty($b['user_id']) && (int) $a['user_id'] === (int) $b['user_id']) {
            return true;
        }
        if (!empty($a['email']) && !empty($b['email']) && $a['email'] === $b['email']) {
            return true;
        }

        return false;
    }

    /**
     * @return array{by_phone: array<string, array>, by_email: array<string, array>, by_user: array<int, array>}
     */
    private static function loadOverridesIndexed(): array
    {
        $empty = ['by_phone' => [], 'by_email' => [], 'by_user' => []];
        if (!Schema::hasTable('student_section_overrides')) {
            return $empty;
        }

        $rows = StudentSectionOverride::query()->get();
        foreach ($rows as $row) {
            $payload = [
                'id' => (int) $row->id,
                'section' => (string) $row->section,
                'message_template' => $row->message_template,
            ];
            if ($row->phone_digits) {
                $empty['by_phone'][(string) $row->phone_digits] = $payload;
            }
            if ($row->email) {
                $empty['by_email'][strtolower((string) $row->email)] = $payload;
            }
            if ($row->user_id) {
                $empty['by_user'][(int) $row->user_id] = $payload;
            }
        }

        return $empty;
    }

    /**
     * @param array{by_phone: array, by_email: array, by_user: array} $overrides
     * @param array<string, mixed> $row
     * @return array{id?:int, section?:string, message_template?:?string}|null
     */
    private static function findOverride(array $overrides, array $row): ?array
    {
        if (!empty($row['phone_digits']) && isset($overrides['by_phone'][$row['phone_digits']])) {
            return $overrides['by_phone'][$row['phone_digits']];
        }
        if (!empty($row['user_id']) && isset($overrides['by_user'][(int) $row['user_id']])) {
            return $overrides['by_user'][(int) $row['user_id']];
        }
        if (!empty($row['email']) && isset($overrides['by_email'][$row['email']])) {
            return $overrides['by_email'][$row['email']];
        }

        return null;
    }

    private static function sourceLabel(bool $hasDemo, bool $hasMentor): string
    {
        if ($hasDemo && $hasMentor) {
            return 'Both';
        }
        if ($hasDemo) {
            return 'Demo';
        }
        if ($hasMentor) {
            return 'Mentor';
        }

        return '—';
    }
}
