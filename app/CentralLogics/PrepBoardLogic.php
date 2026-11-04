<?php

namespace App\CentralLogics;

use App\Mail\PrepBoardDailyUpdateSubmitted;
use App\Mail\PrepBoardReady;
use App\Mail\PrepBoardTargetCycleReady;
use App\Mail\PrepBoardUpdated;
use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorBooking;
use App\Model\Mentor\PrepBoard;
use App\Model\Mentor\PrepBoardDailyTarget;
use App\Model\Mentor\PrepBoardSubject;
use App\Model\Mentor\PrepBoardTargetCycle;
use App\Model\Mentor\PrepBoardUpdate;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PrepBoardLogic
{
    /** Featured MentorKhoj category id → exam_category slug */
    private const CATEGORY_ID_TO_EXAM = [
        25 => 'upsc',
        26 => 'neet',
        59 => 'iit-jee',
        44 => 'ca',
        29 => 'lawyer',
        27 => 'bank-jobs',
        6 => 'tech',
        66 => 'physics',
        70 => 'chemistry',
        71 => 'biology',
        9010 => 'school',
        9011 => 'school',
    ];

    public static function pairHasEligibleBooking(int $mentorId, int $menteeUserId): bool
    {
        return MentorBooking::query()
            ->where('mentor_id', $mentorId)
            ->where('mentee_user_id', $menteeUserId)
            ->where('status', '!=', 'cancelled')
            ->exists();
    }

    public static function latestBookingId(int $mentorId, int $menteeUserId): ?int
    {
        $id = MentorBooking::query()
            ->where('mentor_id', $mentorId)
            ->where('mentee_user_id', $menteeUserId)
            ->where('status', '!=', 'cancelled')
            ->latest('id')
            ->value('id');

        return $id ? (int) $id : null;
    }

    public static function examCategoryFromMentor(Mentor $mentor): ?string
    {
        $raw = $mentor->category_ids ?? null;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw) || count($raw) === 0) {
            return null;
        }

        usort($raw, function ($a, $b) {
            $pa = is_array($a) ? (int) ($a['position'] ?? 999) : 999;
            $pb = is_array($b) ? (int) ($b['position'] ?? 999) : 999;

            return $pa <=> $pb;
        });

        foreach ($raw as $row) {
            $id = is_array($row) ? (int) ($row['id'] ?? 0) : (int) $row;
            if ($id > 0 && isset(self::CATEGORY_ID_TO_EXAM[$id])) {
                return self::CATEGORY_ID_TO_EXAM[$id];
            }
        }

        return null;
    }

    public static function findOrCreateBoard(int $mentorId, int $menteeUserId): PrepBoard
    {
        $board = PrepBoard::firstOrCreate(
            [
                'mentor_id' => $mentorId,
                'mentee_user_id' => $menteeUserId,
            ],
            ['status' => 'draft']
        );

        if (!$board->exam_category) {
            $mentor = Mentor::query()->find($mentorId);
            if ($mentor) {
                $cat = self::examCategoryFromMentor($mentor);
                if ($cat) {
                    $board->exam_category = $cat;
                    $board->save();
                }
            }
        }

        return $board;
    }

    /**
     * @param  mixed  $chapters
     * @return array<int, array{name: string, done: bool}>
     */
    public static function normalizeChapters(mixed $chapters): array
    {
        if (!is_array($chapters)) {
            return [];
        }

        $out = [];
        foreach ($chapters as $row) {
            if (count($out) >= 30) {
                break;
            }
            if (is_string($row)) {
                $name = trim($row);
                if ($name === '') {
                    continue;
                }
                $out[] = ['name' => mb_substr($name, 0, 120), 'done' => false];
                continue;
            }
            if (!is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $out[] = [
                'name' => mb_substr($name, 0, 120),
                'done' => (bool) ($row['done'] ?? false),
            ];
        }

        return $out;
    }

    public static function formatSubject(PrepBoardSubject $subject): array
    {
        return [
            'id' => (int) $subject->id,
            'slot' => $subject->slot,
            'subject_name' => $subject->subject_name,
            'chapters' => self::normalizeChapters($subject->chapters ?? []),
            'sort_order' => (int) $subject->sort_order,
            'theory_text' => $subject->theory_text,
            'practice_text' => $subject->practice_text,
            'note' => $subject->note,
            'hours_devoted' => $subject->hours_devoted,
            'updated_by_user_id' => $subject->updated_by_user_id
                ? (int) $subject->updated_by_user_id
                : null,
            'updated_at' => $subject->updated_at?->toIso8601String(),
        ];
    }

    public static function formatUpdate(PrepBoardUpdate $update): array
    {
        $author = $update->relationLoaded('author') ? $update->author : null;
        $name = $author
            ? trim(($author->f_name ?? '') . ' ' . ($author->l_name ?? ''))
            : null;

        $replier = $update->relationLoaded('repliedBy') ? $update->repliedBy : null;
        $replierName = $replier
            ? trim(($replier->f_name ?? '') . ' ' . ($replier->l_name ?? ''))
            : null;

        return [
            'id' => (int) $update->id,
            'entry_date' => $update->entry_date?->format('Y-m-d'),
            'body' => $update->body,
            'target_progress' => self::normalizeTargetProgress($update->target_progress ?? null),
            'reply_body' => $update->reply_body,
            'replied_at' => $update->replied_at?->toIso8601String(),
            'replied_by_user_id' => $update->replied_by_user_id
                ? (int) $update->replied_by_user_id
                : null,
            'replied_by_name' => $replierName ?: null,
            'author_user_id' => (int) $update->author_user_id,
            'author_role' => $update->author_role,
            'author_name' => $name ?: null,
            'created_at' => $update->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  mixed  $items
     * @return array<int, array{id: string, text: string, done: bool, comment?: string|null}>
     */
    public static function normalizeTargetItems(mixed $items, bool $withDone = true): array
    {
        if (!is_array($items)) {
            return [];
        }

        $out = [];
        foreach ($items as $row) {
            if (count($out) >= 20) {
                break;
            }
            if (!is_array($row)) {
                continue;
            }
            $text = trim((string) ($row['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '') {
                $id = (string) Str::uuid();
            }
            $item = [
                'id' => mb_substr($id, 0, 64),
                'text' => mb_substr($text, 0, 200),
            ];
            if ($withDone) {
                $item['done'] = (bool) ($row['done'] ?? false);
            }
            if (array_key_exists('comment', $row)) {
                $comment = trim((string) ($row['comment'] ?? ''));
                $item['comment'] = $comment === '' ? null : mb_substr($comment, 0, 500);
            }
            $out[] = $item;
        }

        return $out;
    }

    /**
     * @param  mixed  $progress
     * @return array<int, array{id: string, text: string, done: bool, comment: string|null}>
     */
    public static function normalizeTargetProgress(mixed $progress): array
    {
        $items = self::normalizeTargetItems($progress, true);
        return array_map(function ($item) {
            return [
                'id' => $item['id'],
                'text' => $item['text'],
                'done' => (bool) ($item['done'] ?? false),
                'comment' => $item['comment'] ?? null,
            ];
        }, $items);
    }

    /**
     * @param  mixed  $days
     * @return array<int, array{offset: int, items: array<int, array{id: string, text: string}>}>
     */
    public static function normalizeCycleDays(mixed $days): array
    {
        $byOffset = [];
        if (is_array($days)) {
            foreach ($days as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $offset = isset($row['offset']) ? (int) $row['offset'] : -1;
                if ($offset < 0 || $offset > 6) {
                    continue;
                }
                $items = self::normalizeTargetItems($row['items'] ?? [], false);
                $byOffset[$offset] = [
                    'offset' => $offset,
                    'items' => array_map(fn ($i) => [
                        'id' => $i['id'],
                        'text' => $i['text'],
                    ], $items),
                ];
            }
        }

        $out = [];
        for ($i = 0; $i < 7; $i++) {
            $out[] = $byOffset[$i] ?? ['offset' => $i, 'items' => []];
        }

        return $out;
    }

    public static function formatDailyTarget(PrepBoardDailyTarget $target): array
    {
        return [
            'id' => (int) $target->id,
            'target_date' => $target->target_date?->format('Y-m-d'),
            'cycle_id' => $target->cycle_id ? (int) $target->cycle_id : null,
            'day_offset' => $target->day_offset !== null ? (int) $target->day_offset : null,
            'items' => self::normalizeTargetItems($target->items ?? [], true),
            'source' => $target->source ?: 'manual',
            'assigned_by_user_id' => $target->assigned_by_user_id
                ? (int) $target->assigned_by_user_id
                : null,
            'updated_at' => $target->updated_at?->toIso8601String(),
        ];
    }

    public static function formatTargetCycle(PrepBoardTargetCycle $cycle): array
    {
        $start = $cycle->start_date?->format('Y-m-d');
        $days = self::normalizeCycleDays($cycle->days ?? []);
        $dates = [];
        if ($start) {
            $base = Carbon::parse($start);
            foreach ($days as $day) {
                $dates[] = [
                    'offset' => $day['offset'],
                    'date' => $base->copy()->addDays($day['offset'])->toDateString(),
                    'items' => $day['items'],
                ];
            }
        }

        return [
            'id' => (int) $cycle->id,
            'start_date' => $start,
            'days' => $dates,
            'status' => $cycle->status ?: 'draft',
            'published_at' => $cycle->published_at?->toIso8601String(),
            'publish_notified_at' => $cycle->publish_notified_at?->toIso8601String(),
            'assigned_by_user_id' => $cycle->assigned_by_user_id
                ? (int) $cycle->assigned_by_user_id
                : null,
            'updated_at' => $cycle->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Compact heatmap for last 365 days (mentee updates only).
     *
     * @return array<int, array{date: string, count: int}>
     */
    public static function updateHeatmap(PrepBoard $board): array
    {
        $since = Carbon::now()->subDays(365)->toDateString();
        $rows = PrepBoardUpdate::query()
            ->where('prep_board_id', $board->id)
            ->where('author_role', 'mentee')
            ->where('entry_date', '>=', $since)
            ->select('entry_date', DB::raw('COUNT(*) as cnt'))
            ->groupBy('entry_date')
            ->orderBy('entry_date')
            ->get();

        return $rows->map(fn ($r) => [
            'date' => Carbon::parse($r->entry_date)->format('Y-m-d'),
            'count' => (int) $r->cnt,
        ])->values()->all();
    }

    public static function mentorPhotoUrl(?Mentor $mentor): ?string
    {
        if (!$mentor) {
            return null;
        }
        foreach (['photo_url', 'avatar_url', 'image', 'image_full_url'] as $key) {
            $val = $mentor->{$key} ?? null;
            if (is_string($val) && trim($val) !== '') {
                return trim($val);
            }
        }

        return null;
    }

    public static function formatBoard(PrepBoard $board): array
    {
        $board->loadMissing(['subjects', 'updates.author', 'updates.repliedBy', 'mentor', 'mentee']);

        return [
            'id' => (int) $board->id,
            'mentor_id' => (int) $board->mentor_id,
            'mentee_user_id' => (int) $board->mentee_user_id,
            'status' => $board->status,
            'exam_category' => $board->exam_category,
            'mentor_last_edited_at' => $board->mentor_last_edited_at?->toIso8601String(),
            'plan_ready_notified_at' => $board->plan_ready_notified_at?->toIso8601String(),
            'mentor' => $board->mentor ? [
                'id' => (int) $board->mentor->id,
                'username' => $board->mentor->username,
                'display_name' => $board->mentor->display_name,
                'headline' => $board->mentor->headline,
                'photo_url' => self::mentorPhotoUrl($board->mentor),
            ] : null,
            'mentee' => $board->mentee ? [
                'id' => (int) $board->mentee->id,
                'name' => trim(($board->mentee->f_name ?? '') . ' ' . ($board->mentee->l_name ?? '')) ?: ($board->mentee->name ?? null),
                'first_name' => $board->mentee->f_name,
            ] : null,
            'subjects' => $board->subjects->map(fn ($s) => self::formatSubject($s))->values()->all(),
            'updates' => $board->updates->take(50)->map(fn ($u) => self::formatUpdate($u))->values()->all(),
            'update_heatmap' => self::updateHeatmap($board),
            'daily_targets' => self::recentDailyTargets($board, 30),
            'updated_at' => $board->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function recentDailyTargets(PrepBoard $board, int $days = 30): array
    {
        $since = Carbon::now()->subDays(max(1, $days))->toDateString();
        $rows = PrepBoardDailyTarget::query()
            ->where('prep_board_id', $board->id)
            ->where('target_date', '>=', $since)
            ->orderByDesc('target_date')
            ->limit(60)
            ->get();

        return $rows->map(fn ($t) => self::formatDailyTarget($t))->values()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $subjects
     */
    public static function syncSubjects(PrepBoard $board, array $subjects, int $userId): void
    {
        $keptIds = [];

        foreach (array_values($subjects) as $index => $row) {
            $slot = ($row['slot'] ?? '') === 'current' ? 'current' : 'revision';
            $name = trim((string) ($row['subject_name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $hours = $row['hours_devoted'] ?? null;
            if ($hours === '' || $hours === null) {
                $hours = null;
            } else {
                $hours = round((float) $hours, 2);
            }

            $payload = [
                'slot' => $slot,
                'subject_name' => mb_substr($name, 0, 255),
                'chapters' => self::normalizeChapters($row['chapters'] ?? []),
                'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : $index,
                'theory_text' => self::nullableText($row['theory_text'] ?? null, 5000),
                'practice_text' => self::nullableText($row['practice_text'] ?? null, 5000),
                'note' => self::nullableText($row['note'] ?? null, 5000),
                'hours_devoted' => $hours,
                'updated_by_user_id' => $userId,
            ];

            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id > 0) {
                $existing = PrepBoardSubject::query()
                    ->where('prep_board_id', $board->id)
                    ->where('id', $id)
                    ->first();
                if ($existing) {
                    $existing->update($payload);
                    $keptIds[] = $existing->id;
                    continue;
                }
            }

            $created = PrepBoardSubject::create(array_merge($payload, [
                'prep_board_id' => $board->id,
            ]));
            $keptIds[] = $created->id;
        }

        PrepBoardSubject::query()
            ->where('prep_board_id', $board->id)
            ->when(count($keptIds) > 0, fn ($q) => $q->whereNotIn('id', $keptIds))
            ->when(count($keptIds) === 0, fn ($q) => $q)
            ->delete();
    }

    private static function nullableText(mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, $max);
    }

    public static function boardLink(PrepBoard $board, bool $forMentor): string
    {
        $siteUrl = rtrim((string) config('app.mentorkhoj_site_url', 'https://www.mentorkhoj.com'), '/');

        return $forMentor
            ? "{$siteUrl}/mentor/dashboard/prep-board/{$board->mentee_user_id}"
            : "{$siteUrl}/account/prep-board/{$board->mentor_id}";
    }

    public static function dailyUpdateLink(PrepBoard $board, bool $forMentor): string
    {
        $siteUrl = rtrim((string) config('app.mentorkhoj_site_url', 'https://www.mentorkhoj.com'), '/');

        return $forMentor
            ? "{$siteUrl}/mentor/dashboard/daily-update/{$board->mentee_user_id}"
            : "{$siteUrl}/account/daily-update/{$board->mentor_id}";
    }

    public static function notifyMentorEdit(PrepBoard $board, bool $becameReady): void
    {
        $board->loadMissing(['mentor', 'mentee']);
        $menteeId = (int) $board->mentee_user_id;
        if ($menteeId <= 0) {
            return;
        }

        $mentorName = $board->mentor?->display_name ?: 'Your mentor';
        $link = self::boardLink($board, false);
        $bookingId = self::latestBookingId((int) $board->mentor_id, $menteeId);

        if ($becameReady && !$board->plan_ready_notified_at) {
            UserNotificationLogic::create(
                $menteeId,
                'prep_board_ready',
                'Your PrepBoard is ready',
                'Your study plan is ready. Open PrepBoard to review it.',
                $link,
                $bookingId
            );

            self::sendPrepBoardMail($board->mentee?->email, new PrepBoardReady($board));

            $board->plan_ready_notified_at = now();
            $board->last_update_notified_at = now();
            $board->save();

            return;
        }

        $throttleOk = !$board->last_update_notified_at
            || $board->last_update_notified_at->lt(now()->subMinutes(15));

        UserNotificationLogic::create(
            $menteeId,
            'prep_board_updated',
            'PrepBoard updated',
            "{$mentorName} updated your PrepBoard.",
            $link,
            $bookingId
        );

        if ($throttleOk) {
            self::sendPrepBoardMail($board->mentee?->email, new PrepBoardUpdated($board));
            $board->last_update_notified_at = now();
            $board->save();
        }
    }

    /**
     * Ops CC on every PrepBoard-section email — same address as DemoMentorAssigned /
     * FormMailLogic notify (MENTORKHOJ_NOTIFY_EMAIL → mentorkhoj@gmail.com).
     */
    public static function prepBoardMailCc(): array
    {
        $cc = '';
        if (class_exists(FormMailLogic::class)) {
            $cc = trim((string) FormMailLogic::notifyEmail());
        }
        if ($cc === '') {
            $cc = trim((string) env('MENTORKHOJ_NOTIFY_EMAIL', 'mentorkhoj@gmail.com'));
        }
        if ($cc === '' || !filter_var($cc, FILTER_VALIDATE_EMAIL)) {
            $cc = 'mentorkhoj@gmail.com';
        }

        return [$cc];
    }

    public static function adminOpsEmail(): string
    {
        if (class_exists(FormMailLogic::class)) {
            $admin = trim((string) FormMailLogic::adminEmail());
            if ($admin !== '' && filter_var($admin, FILTER_VALIDATE_EMAIL)) {
                return $admin;
            }
        }

        return (string) (
            env('DEMO_BOOKING_ADMIN_EMAIL')
            ?: env('MAIL_FROM_ADDRESS')
            ?: config('mail.from.address')
            ?: 'admin@mentorkhoj.com'
        );
    }

    /**
     * Never throws — mail failures must not break PrepBoard APIs.
     *
     * @param  string|array<int, string>|null  $to
     */
    public static function sendPrepBoardMail($to, $mailable): void
    {
        $recipients = [];
        foreach (is_array($to) ? $to : [$to] as $addr) {
            $email = is_string($addr) ? trim($addr) : '';
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $recipients[] = $email;
            }
        }
        if (count($recipients) === 0) {
            return;
        }

        try {
            Mail::to($recipients)
                ->cc(self::prepBoardMailCc())
                ->send($mailable);
        } catch (\Throwable $e) {
            // Don't fail the API if mail transport errors.
        }
    }

    public static function notifyStudentDailyUpdate(PrepBoard $board, ?PrepBoardUpdate $update = null): void
    {
        $board->loadMissing(['mentor', 'mentee']);
        $mentor = $board->mentor;
        if (!$mentor || !(int) ($mentor->user_id ?? 0)) {
            return;
        }

        $studentName = $board->mentee
            ? (trim(($board->mentee->f_name ?? '') . ' ' . ($board->mentee->l_name ?? '')) ?: 'Your student')
            : 'Your student';
        $link = self::dailyUpdateLink($board, true);
        $bookingId = self::latestBookingId((int) $board->mentor_id, (int) $board->mentee_user_id);

        UserNotificationLogic::create(
            (int) $mentor->user_id,
            'prep_board_daily_update',
            'Daily update from student',
            "{$studentName} posted a daily update. Open Daily Update to reply.",
            $link,
            $bookingId
        );

        if ($update) {
            $mentorUser = User::query()->find((int) $mentor->user_id);
            $mentorEmail = $mentorUser?->email;
            $adminEmail = self::adminOpsEmail();
            $to = array_values(array_unique(array_filter([
                is_string($mentorEmail) ? trim($mentorEmail) : null,
                $adminEmail,
            ])));
            self::sendPrepBoardMail($to, new PrepBoardDailyUpdateSubmitted($board, $update));
        }
    }

    public static function notifyMenteeReply(PrepBoard $board): void
    {
        $board->loadMissing(['mentor', 'mentee']);
        $menteeId = (int) $board->mentee_user_id;
        if ($menteeId <= 0) {
            return;
        }

        $mentorName = $board->mentor?->display_name ?: 'Your mentor';
        $link = self::dailyUpdateLink($board, false);
        $bookingId = self::latestBookingId((int) $board->mentor_id, $menteeId);

        UserNotificationLogic::create(
            $menteeId,
            'prep_board_daily_reply',
            'Mentor replied to your update',
            "{$mentorName} replied on your Daily Update.",
            $link,
            $bookingId
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listForMentee(int $menteeUserId): array
    {
        $mentorIds = MentorBooking::query()
            ->where('mentee_user_id', $menteeUserId)
            ->where('status', '!=', 'cancelled')
            ->distinct()
            ->pluck('mentor_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($mentorIds) === 0) {
            return [];
        }

        $mentors = Mentor::query()->whereIn('id', $mentorIds)->get()->keyBy('id');
        $boards = PrepBoard::query()
            ->where('mentee_user_id', $menteeUserId)
            ->whereIn('mentor_id', $mentorIds)
            ->get()
            ->keyBy('mentor_id');

        $out = [];
        foreach ($mentorIds as $mentorId) {
            $mentor = $mentors->get($mentorId);
            $board = $boards->get($mentorId);
            $out[] = [
                'mentor_id' => $mentorId,
                'mentee_user_id' => $menteeUserId,
                'status' => $board?->status ?? 'draft',
                'exam_category' => $board?->exam_category,
                'board_id' => $board ? (int) $board->id : null,
                'subject_count' => $board
                    ? (int) PrepBoardSubject::where('prep_board_id', $board->id)->count()
                    : 0,
                'mentor' => $mentor ? [
                    'id' => (int) $mentor->id,
                    'username' => $mentor->username,
                    'display_name' => $mentor->display_name,
                    'headline' => $mentor->headline,
                    'photo_url' => self::mentorPhotoUrl($mentor),
                ] : null,
                'updated_at' => $board?->updated_at?->toIso8601String(),
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listForMentor(int $mentorId): array
    {
        $menteeIds = MentorBooking::query()
            ->where('mentor_id', $mentorId)
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('mentee_user_id')
            ->distinct()
            ->pluck('mentee_user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($menteeIds) === 0) {
            return [];
        }

        $users = User::query()->whereIn('id', $menteeIds)->get()->keyBy('id');
        $boards = PrepBoard::query()
            ->where('mentor_id', $mentorId)
            ->whereIn('mentee_user_id', $menteeIds)
            ->get()
            ->keyBy('mentee_user_id');

        $out = [];
        foreach ($menteeIds as $menteeId) {
            $user = $users->get($menteeId);
            $board = $boards->get($menteeId);
            $name = $user
                ? (trim(($user->f_name ?? '') . ' ' . ($user->l_name ?? '')) ?: ($user->name ?? 'Student'))
                : 'Student';
            $out[] = [
                'mentor_id' => $mentorId,
                'mentee_user_id' => $menteeId,
                'status' => $board?->status ?? 'draft',
                'exam_category' => $board?->exam_category,
                'board_id' => $board ? (int) $board->id : null,
                'subject_count' => $board
                    ? (int) PrepBoardSubject::where('prep_board_id', $board->id)->count()
                    : 0,
                'mentee' => [
                    'id' => $menteeId,
                    'name' => $name,
                    'first_name' => $user?->f_name,
                ],
                'updated_at' => $board?->updated_at?->toIso8601String(),
            ];
        }

        return $out;
    }

    public static function addUpdate(
        PrepBoard $board,
        int $userId,
        string $role,
        string $entryDate,
        string $body,
        ?array $targetProgress = null
    ): PrepBoardUpdate {
        $progress = $targetProgress !== null
            ? self::normalizeTargetProgress($targetProgress)
            : null;

        $update = PrepBoardUpdate::create([
            'prep_board_id' => $board->id,
            'entry_date' => Carbon::parse($entryDate)->toDateString(),
            'body' => mb_substr(trim($body), 0, 5000),
            'target_progress' => $progress,
            'author_user_id' => $userId,
            'author_role' => $role === 'mentor' ? 'mentor' : 'mentee',
        ]);

        if ($progress !== null && count($progress) > 0) {
            self::syncDoneFromProgress($board, $update->entry_date->format('Y-m-d'), $progress);
        }

        return $update;
    }

    /**
     * @param  array<int, array{id: string, text: string, done: bool, comment?: string|null}>  $progress
     */
    public static function syncDoneFromProgress(PrepBoard $board, string $date, array $progress): void
    {
        $target = PrepBoardDailyTarget::query()
            ->where('prep_board_id', $board->id)
            ->whereDate('target_date', $date)
            ->first();
        if (!$target) {
            return;
        }

        $doneById = [];
        foreach ($progress as $row) {
            $doneById[$row['id']] = (bool) ($row['done'] ?? false);
        }

        $items = self::normalizeTargetItems($target->items ?? [], true);
        foreach ($items as &$item) {
            if (array_key_exists($item['id'], $doneById)) {
                $item['done'] = $doneById[$item['id']];
            }
        }
        unset($item);

        $target->items = $items;
        $target->save();
    }

    public static function replyToUpdate(
        PrepBoardUpdate $update,
        int $mentorUserId,
        string $replyBody
    ): PrepBoardUpdate {
        $update->reply_body = mb_substr(trim($replyBody), 0, 5000);
        $update->replied_at = now();
        $update->replied_by_user_id = $mentorUserId;
        $update->save();

        return $update->fresh(['author', 'repliedBy']);
    }

    public static function getOrCreateTargetCycle(PrepBoard $board, string $startDate): PrepBoardTargetCycle
    {
        $start = Carbon::parse($startDate)->toDateString();

        return PrepBoardTargetCycle::firstOrCreate(
            [
                'prep_board_id' => $board->id,
                'start_date' => $start,
            ],
            [
                'days' => self::normalizeCycleDays([]),
                'status' => 'draft',
            ]
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $days
     */
    public static function saveTargetCycleDraft(
        PrepBoard $board,
        string $startDate,
        array $days,
        int $userId
    ): PrepBoardTargetCycle {
        $cycle = self::getOrCreateTargetCycle($board, $startDate);
        $cycle->days = self::normalizeCycleDays($days);
        $cycle->assigned_by_user_id = $userId;
        if ($cycle->status !== 'published') {
            $cycle->status = 'draft';
        }
        $cycle->save();

        return $cycle->fresh();
    }

    public static function publishTargetCycle(
        PrepBoard $board,
        string $startDate,
        int $userId,
        ?array $days = null
    ): PrepBoardTargetCycle {
        $cycle = self::getOrCreateTargetCycle($board, $startDate);
        if ($days !== null) {
            $cycle->days = self::normalizeCycleDays($days);
        } else {
            $cycle->days = self::normalizeCycleDays($cycle->days ?? []);
        }
        $cycle->status = 'published';
        $cycle->published_at = now();
        $cycle->assigned_by_user_id = $userId;
        $cycle->save();

        $normalized = self::normalizeCycleDays($cycle->days);
        $base = Carbon::parse($cycle->start_date);

        foreach ($normalized as $day) {
            $date = $base->copy()->addDays($day['offset'])->toDateString();
            $existing = PrepBoardDailyTarget::query()
                ->where('prep_board_id', $board->id)
                ->whereDate('target_date', $date)
                ->first();

            $newItems = [];
            $prevDone = [];
            if ($existing) {
                foreach (self::normalizeTargetItems($existing->items ?? [], true) as $old) {
                    $prevDone[$old['id']] = (bool) ($old['done'] ?? false);
                }
            }
            foreach ($day['items'] as $item) {
                $newItems[] = [
                    'id' => $item['id'],
                    'text' => $item['text'],
                    'done' => (bool) ($prevDone[$item['id']] ?? false),
                ];
            }

            PrepBoardDailyTarget::updateOrCreate(
                [
                    'prep_board_id' => $board->id,
                    'target_date' => $date,
                ],
                [
                    'cycle_id' => $cycle->id,
                    'day_offset' => $day['offset'],
                    'items' => $newItems,
                    'source' => 'cycle',
                    'assigned_by_user_id' => $userId,
                ]
            );
        }

        $shouldNotify = !$cycle->publish_notified_at
            || $cycle->publish_notified_at->lt(now()->subMinutes(15));

        if ($shouldNotify) {
            self::notifyTargetCyclePublished($board, $cycle);
            $cycle->publish_notified_at = now();
            $cycle->save();
        }

        return $cycle->fresh();
    }

    public static function notifyTargetCyclePublished(PrepBoard $board, PrepBoardTargetCycle $cycle): void
    {
        $board->loadMissing(['mentor', 'mentee']);
        $menteeId = (int) $board->mentee_user_id;
        if ($menteeId <= 0) {
            return;
        }

        $link = self::dailyUpdateLink($board, false);
        $bookingId = self::latestBookingId((int) $board->mentor_id, $menteeId);

        UserNotificationLogic::create(
            $menteeId,
            'prep_board_weekly_schedule_ready',
            'Your study schedule is set',
            'Your mentor set 7-day targets. Open Daily Update and focus on each day.',
            $link,
            $bookingId
        );

        self::sendPrepBoardMail($board->mentee?->email, new PrepBoardTargetCycleReady($board, $cycle));
    }

    public static function copyTargetCycle(
        PrepBoard $board,
        string $fromStart,
        string $toStart,
        int $userId
    ): PrepBoardTargetCycle {
        $source = PrepBoardTargetCycle::query()
            ->where('prep_board_id', $board->id)
            ->whereDate('start_date', Carbon::parse($fromStart)->toDateString())
            ->first();

        $days = self::normalizeCycleDays($source?->days ?? []);
        // Fresh item ids so done state does not collide across cycles
        foreach ($days as &$day) {
            foreach ($day['items'] as &$item) {
                $item['id'] = (string) Str::uuid();
            }
            unset($item);
        }
        unset($day);

        return self::saveTargetCycleDraft($board, $toStart, $days, $userId);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public static function upsertDailyTarget(
        PrepBoard $board,
        string $date,
        array $items,
        int $userId
    ): PrepBoardDailyTarget {
        $dateStr = Carbon::parse($date)->toDateString();
        $existing = PrepBoardDailyTarget::query()
            ->where('prep_board_id', $board->id)
            ->whereDate('target_date', $dateStr)
            ->first();

        $prevDone = [];
        if ($existing) {
            foreach (self::normalizeTargetItems($existing->items ?? [], true) as $old) {
                $prevDone[$old['id']] = (bool) ($old['done'] ?? false);
            }
        }

        $normalized = self::normalizeTargetItems($items, true);
        foreach ($normalized as &$item) {
            if (!array_key_exists('done', $item) || $item['done'] === false) {
                $item['done'] = (bool) ($prevDone[$item['id']] ?? $item['done'] ?? false);
            }
        }
        unset($item);

        return PrepBoardDailyTarget::updateOrCreate(
            [
                'prep_board_id' => $board->id,
                'target_date' => $dateStr,
            ],
            [
                'items' => $normalized,
                'source' => $existing?->source ?: 'manual',
                'cycle_id' => $existing?->cycle_id,
                'day_offset' => $existing?->day_offset,
                'assigned_by_user_id' => $userId,
            ]
        )->fresh();
    }

    public static function copyDailyTarget(
        PrepBoard $board,
        string $fromDate,
        string $toDate,
        int $userId
    ): PrepBoardDailyTarget {
        $source = PrepBoardDailyTarget::query()
            ->where('prep_board_id', $board->id)
            ->whereDate('target_date', Carbon::parse($fromDate)->toDateString())
            ->first();

        $items = [];
        foreach (self::normalizeTargetItems($source?->items ?? [], true) as $item) {
            $items[] = [
                'id' => (string) Str::uuid(),
                'text' => $item['text'],
                'done' => false,
            ];
        }

        return self::upsertDailyTarget($board, $toDate, $items, $userId);
    }

    public static function listDailyTargets(PrepBoard $board, string $from, string $to): array
    {
        $rows = PrepBoardDailyTarget::query()
            ->where('prep_board_id', $board->id)
            ->whereDate('target_date', '>=', Carbon::parse($from)->toDateString())
            ->whereDate('target_date', '<=', Carbon::parse($to)->toDateString())
            ->orderBy('target_date')
            ->get();

        return $rows->map(fn ($t) => self::formatDailyTarget($t))->values()->all();
    }

    public static function findDailyTarget(PrepBoard $board, string $date): ?PrepBoardDailyTarget
    {
        return PrepBoardDailyTarget::query()
            ->where('prep_board_id', $board->id)
            ->whereDate('target_date', Carbon::parse($date)->toDateString())
            ->first();
    }
}
