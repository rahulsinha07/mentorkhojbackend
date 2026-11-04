<?php

namespace App\Http\Controllers\Api\V1\Mentor;

use App\CentralLogics\Helpers;
use App\CentralLogics\PrepBoardLogic;
use App\Http\Controllers\Controller;
use App\Model\Mentor\Mentor;
use App\Model\Mentor\PrepBoardUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PrepBoardController extends Controller
{
    private function mentorForUser(Request $request): ?Mentor
    {
        return Mentor::where('user_id', $request->user()->id)->first();
    }

    public function myList(Request $request): JsonResponse
    {
        $boards = PrepBoardLogic::listForMentee((int) $request->user()->id);

        return response()->json(['boards' => $boards]);
    }

    public function mentorList(Request $request): JsonResponse
    {
        $mentor = $this->mentorForUser($request);
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $boards = PrepBoardLogic::listForMentor((int) $mentor->id);

        return response()->json(['boards' => $boards]);
    }

    public function myShow(Request $request, int $mentorId): JsonResponse
    {
        $menteeId = (int) $request->user()->id;

        if (!PrepBoardLogic::pairHasEligibleBooking($mentorId, $menteeId)) {
            return response()->json(['errors' => [['message' => 'PrepBoard unlocks after you book this mentor.']]], 403);
        }

        $board = PrepBoardLogic::findOrCreateBoard($mentorId, $menteeId);

        return response()->json(['board' => PrepBoardLogic::formatBoard($board->fresh())]);
    }

    public function mentorShow(Request $request, int $menteeUserId): JsonResponse
    {
        $mentor = $this->mentorForUser($request);
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        if (!PrepBoardLogic::pairHasEligibleBooking((int) $mentor->id, $menteeUserId)) {
            return response()->json(['errors' => [['message' => 'PrepBoard unlocks after a booking with this student.']]], 403);
        }

        $board = PrepBoardLogic::findOrCreateBoard((int) $mentor->id, $menteeUserId);

        return response()->json(['board' => PrepBoardLogic::formatBoard($board->fresh())]);
    }

    public function myUpdate(Request $request, int $mentorId): JsonResponse
    {
        $menteeId = (int) $request->user()->id;

        if (!PrepBoardLogic::pairHasEligibleBooking($mentorId, $menteeId)) {
            return response()->json(['errors' => [['message' => 'PrepBoard unlocks after you book this mentor.']]], 403);
        }

        return $this->saveBoard($request, $mentorId, $menteeId, false);
    }

    public function mentorUpdate(Request $request, int $menteeUserId): JsonResponse
    {
        $mentor = $this->mentorForUser($request);
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        if (!PrepBoardLogic::pairHasEligibleBooking((int) $mentor->id, $menteeUserId)) {
            return response()->json(['errors' => [['message' => 'PrepBoard unlocks after a booking with this student.']]], 403);
        }

        return $this->saveBoard($request, (int) $mentor->id, $menteeUserId, true);
    }

    private function saveBoard(Request $request, int $mentorId, int $menteeUserId, bool $asMentor): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'subjects' => 'required|array',
            'subjects.*.id' => 'nullable|integer',
            'subjects.*.slot' => 'required|in:revision,current',
            'subjects.*.subject_name' => 'required|string|max:255',
            'subjects.*.chapters' => 'nullable|array|max:30',
            'subjects.*.chapters.*.name' => 'required_with:subjects.*.chapters|string|max:120',
            'subjects.*.chapters.*.done' => 'nullable|boolean',
            'subjects.*.sort_order' => 'nullable|integer|min:0',
            'subjects.*.theory_text' => 'nullable|string|max:5000',
            'subjects.*.practice_text' => 'nullable|string|max:5000',
            'subjects.*.note' => 'nullable|string|max:5000',
            'subjects.*.hours_devoted' => 'nullable|numeric|min:0|max:9999',
            'status' => 'nullable|in:draft,ready',
            'exam_category' => 'nullable|string|max:32',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $userId = (int) $request->user()->id;
        $board = PrepBoardLogic::findOrCreateBoard($mentorId, $menteeUserId);
        $wasReady = $board->status === 'ready';
        $subjectCountBefore = $board->subjects()->count();

        DB::transaction(function () use ($board, $request, $userId, $asMentor) {
            PrepBoardLogic::syncSubjects($board, $request->input('subjects', []), $userId);

            if ($request->filled('exam_category')) {
                $board->exam_category = mb_substr(trim((string) $request->input('exam_category')), 0, 32);
            }

            $status = $request->input('status');
            if ($asMentor) {
                $board->mentor_last_edited_at = now();
                $hasSubjects = count($request->input('subjects', [])) > 0
                    || $board->subjects()->count() > 0;
                if ($status === 'draft') {
                    $board->status = 'draft';
                } elseif ($status === 'ready' || ($hasSubjects && $board->status === 'draft')) {
                    $board->status = 'ready';
                }
            }

            $board->touch();
            $board->save();
        });

        $board = $board->fresh(['subjects', 'updates.author', 'updates.repliedBy', 'mentor', 'mentee']);
        $becameReady = $asMentor && !$wasReady && $board->status === 'ready';
        $subjectsChanged = $asMentor && (
            $becameReady
            || (int) $board->subjects()->count() !== $subjectCountBefore
            || $request->has('subjects')
        );

        if ($asMentor && ($becameReady || $subjectsChanged)) {
            PrepBoardLogic::notifyMentorEdit($board, $becameReady);
            $board = $board->fresh(['subjects', 'updates.author', 'updates.repliedBy', 'mentor', 'mentee']);
        }

        return response()->json([
            'message' => $asMentor
                ? ($becameReady ? 'PrepBoard saved. Student has been notified.' : 'PrepBoard updated.')
                : 'PrepBoard saved.',
            'board' => PrepBoardLogic::formatBoard($board),
            'notified' => $asMentor && ($becameReady || $subjectsChanged),
        ]);
    }

    public function myAddUpdate(Request $request, int $mentorId): JsonResponse
    {
        $menteeId = (int) $request->user()->id;

        if (!PrepBoardLogic::pairHasEligibleBooking($mentorId, $menteeId)) {
            return response()->json(['errors' => [['message' => 'PrepBoard unlocks after you book this mentor.']]], 403);
        }

        return $this->storeUpdate($request, $mentorId, $menteeId);
    }

    /** Mentors cannot post daily updates — students only. */
    public function mentorAddUpdate(Request $request, int $menteeUserId): JsonResponse
    {
        return response()->json([
            'errors' => [['message' => 'Only students can post daily updates. You can reply to their entries.']],
        ], 403);
    }

    private function storeUpdate(Request $request, int $mentorId, int $menteeUserId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'entry_date' => 'required|date',
            'body' => 'nullable|string|max:5000',
            'target_progress' => 'nullable|array|max:20',
            'target_progress.*.id' => 'nullable|string|max:64',
            'target_progress.*.text' => 'required_with:target_progress|string|max:200',
            'target_progress.*.done' => 'nullable|boolean',
            'target_progress.*.comment' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $body = trim((string) $request->input('body', ''));
        $progress = $request->input('target_progress');
        $hasProgress = is_array($progress) && count($progress) > 0;

        // Backward compatible: body-only posts still work; with targets, note is optional.
        if (!$hasProgress && $body === '') {
            return response()->json([
                'errors' => [['code' => 'body', 'message' => 'Write what you did today.']],
            ], 403);
        }

        $board = PrepBoardLogic::findOrCreateBoard($mentorId, $menteeUserId);
        $update = PrepBoardLogic::addUpdate(
            $board,
            (int) $request->user()->id,
            'mentee',
            (string) $request->input('entry_date'),
            $body !== '' ? $body : ($hasProgress ? 'Checked in on daily targets.' : ''),
            $hasProgress ? $progress : null
        );
        $update->load(['author', 'repliedBy']);

        PrepBoardLogic::notifyStudentDailyUpdate($board, $update);

        return response()->json([
            'message' => 'Update added.',
            'update' => PrepBoardLogic::formatUpdate($update),
            'board' => PrepBoardLogic::formatBoard($board->fresh()),
        ], 201);
    }

    // ─── Target cycles & daily targets (additive) ───────────────────────────

    private function resolvePairBoard(Request $request, bool $asMentor, int $pairId): array
    {
        if ($asMentor) {
            $mentor = $this->mentorForUser($request);
            if (!$mentor) {
                return [null, response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404)];
            }
            if (!PrepBoardLogic::pairHasEligibleBooking((int) $mentor->id, $pairId)) {
                return [null, response()->json(['errors' => [['message' => 'PrepBoard unlocks after a booking with this student.']]], 403)];
            }

            return [PrepBoardLogic::findOrCreateBoard((int) $mentor->id, $pairId), null];
        }

        $menteeId = (int) $request->user()->id;
        if (!PrepBoardLogic::pairHasEligibleBooking($pairId, $menteeId)) {
            return [null, response()->json(['errors' => [['message' => 'PrepBoard unlocks after you book this mentor.']]], 403)];
        }

        return [PrepBoardLogic::findOrCreateBoard($pairId, $menteeId), null];
    }

    public function myListDailyTargets(Request $request, int $mentorId): JsonResponse
    {
        return $this->listDailyTargets($request, false, $mentorId);
    }

    public function mentorListDailyTargets(Request $request, int $menteeUserId): JsonResponse
    {
        return $this->listDailyTargets($request, true, $menteeUserId);
    }

    private function listDailyTargets(Request $request, bool $asMentor, int $pairId): JsonResponse
    {
        [$board, $error] = $this->resolvePairBoard($request, $asMentor, $pairId);
        if ($error) {
            return $error;
        }

        $from = $request->query('from', now()->subDays(14)->toDateString());
        $to = $request->query('to', now()->addDays(14)->toDateString());

        return response()->json([
            'targets' => PrepBoardLogic::listDailyTargets($board, (string) $from, (string) $to),
        ]);
    }

    public function myShowDailyTarget(Request $request, int $mentorId, string $date): JsonResponse
    {
        return $this->showDailyTarget($request, false, $mentorId, $date);
    }

    public function mentorShowDailyTarget(Request $request, int $menteeUserId, string $date): JsonResponse
    {
        return $this->showDailyTarget($request, true, $menteeUserId, $date);
    }

    private function showDailyTarget(Request $request, bool $asMentor, int $pairId, string $date): JsonResponse
    {
        [$board, $error] = $this->resolvePairBoard($request, $asMentor, $pairId);
        if ($error) {
            return $error;
        }

        $target = PrepBoardLogic::findDailyTarget($board, $date);

        return response()->json([
            'target' => $target ? PrepBoardLogic::formatDailyTarget($target) : null,
        ]);
    }

    public function mentorUpsertDailyTarget(Request $request, int $menteeUserId, string $date): JsonResponse
    {
        [$board, $error] = $this->resolvePairBoard($request, true, $menteeUserId);
        if ($error) {
            return $error;
        }

        $validator = Validator::make($request->all(), [
            'items' => 'required|array|max:20',
            'items.*.id' => 'nullable|string|max:64',
            'items.*.text' => 'required|string|max:200',
            'items.*.done' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $target = PrepBoardLogic::upsertDailyTarget(
            $board,
            $date,
            $request->input('items', []),
            (int) $request->user()->id
        );

        return response()->json([
            'message' => 'Targets saved.',
            'target' => PrepBoardLogic::formatDailyTarget($target),
            'board' => PrepBoardLogic::formatBoard($board->fresh()),
        ]);
    }

    public function mentorCopyDailyTarget(Request $request, int $menteeUserId): JsonResponse
    {
        [$board, $error] = $this->resolvePairBoard($request, true, $menteeUserId);
        if ($error) {
            return $error;
        }

        $validator = Validator::make($request->all(), [
            'from_date' => 'required|date',
            'to_date' => 'required|date',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $target = PrepBoardLogic::copyDailyTarget(
            $board,
            (string) $request->input('from_date'),
            (string) $request->input('to_date'),
            (int) $request->user()->id
        );

        return response()->json([
            'message' => 'Targets copied.',
            'target' => PrepBoardLogic::formatDailyTarget($target),
            'board' => PrepBoardLogic::formatBoard($board->fresh()),
        ]);
    }

    public function myShowTargetCycle(Request $request, int $mentorId, string $startDate): JsonResponse
    {
        return $this->showTargetCycle($request, false, $mentorId, $startDate);
    }

    public function mentorShowTargetCycle(Request $request, int $menteeUserId, string $startDate): JsonResponse
    {
        return $this->showTargetCycle($request, true, $menteeUserId, $startDate);
    }

    private function showTargetCycle(Request $request, bool $asMentor, int $pairId, string $startDate): JsonResponse
    {
        [$board, $error] = $this->resolvePairBoard($request, $asMentor, $pairId);
        if ($error) {
            return $error;
        }

        $cycle = \App\Model\Mentor\PrepBoardTargetCycle::query()
            ->where('prep_board_id', $board->id)
            ->whereDate('start_date', $startDate)
            ->first();

        return response()->json([
            'cycle' => $cycle
                ? PrepBoardLogic::formatTargetCycle($cycle)
                : PrepBoardLogic::formatTargetCycle(PrepBoardLogic::getOrCreateTargetCycle($board, $startDate)),
        ]);
    }

    public function mentorSaveTargetCycle(Request $request, int $menteeUserId, string $startDate): JsonResponse
    {
        [$board, $error] = $this->resolvePairBoard($request, true, $menteeUserId);
        if ($error) {
            return $error;
        }

        $validator = Validator::make($request->all(), [
            'days' => 'required|array|max:7',
            'days.*.offset' => 'required|integer|min:0|max:6',
            'days.*.items' => 'nullable|array|max:20',
            'days.*.items.*.id' => 'nullable|string|max:64',
            'days.*.items.*.text' => 'required_with:days.*.items|string|max:200',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $cycle = PrepBoardLogic::saveTargetCycleDraft(
            $board,
            $startDate,
            $request->input('days', []),
            (int) $request->user()->id
        );

        return response()->json([
            'message' => 'Draft saved.',
            'cycle' => PrepBoardLogic::formatTargetCycle($cycle),
        ]);
    }

    public function mentorPublishTargetCycle(Request $request, int $menteeUserId, string $startDate): JsonResponse
    {
        [$board, $error] = $this->resolvePairBoard($request, true, $menteeUserId);
        if ($error) {
            return $error;
        }

        $validator = Validator::make($request->all(), [
            'days' => 'nullable|array|max:7',
            'days.*.offset' => 'required_with:days|integer|min:0|max:6',
            'days.*.items' => 'nullable|array|max:20',
            'days.*.items.*.id' => 'nullable|string|max:64',
            'days.*.items.*.text' => 'required_with:days.*.items|string|max:200',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $cycle = PrepBoardLogic::publishTargetCycle(
            $board,
            $startDate,
            (int) $request->user()->id,
            $request->has('days') ? $request->input('days', []) : null
        );

        return response()->json([
            'message' => 'Schedule published. Student has been notified.',
            'cycle' => PrepBoardLogic::formatTargetCycle($cycle),
            'board' => PrepBoardLogic::formatBoard($board->fresh()),
            'notified' => true,
        ]);
    }

    public function mentorCopyTargetCycle(Request $request, int $menteeUserId): JsonResponse
    {
        [$board, $error] = $this->resolvePairBoard($request, true, $menteeUserId);
        if ($error) {
            return $error;
        }

        $validator = Validator::make($request->all(), [
            'from_start' => 'required|date',
            'to_start' => 'required|date',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $cycle = PrepBoardLogic::copyTargetCycle(
            $board,
            (string) $request->input('from_start'),
            (string) $request->input('to_start'),
            (int) $request->user()->id
        );

        return response()->json([
            'message' => 'Week copied.',
            'cycle' => PrepBoardLogic::formatTargetCycle($cycle),
        ]);
    }

    public function mentorReplyUpdate(Request $request, int $menteeUserId, int $updateId): JsonResponse
    {
        $mentor = $this->mentorForUser($request);
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        if (!PrepBoardLogic::pairHasEligibleBooking((int) $mentor->id, $menteeUserId)) {
            return response()->json(['errors' => [['message' => 'PrepBoard unlocks after a booking with this student.']]], 403);
        }

        $validator = Validator::make($request->all(), [
            'reply_body' => 'required|string|max:5000',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $board = PrepBoardLogic::findOrCreateBoard((int) $mentor->id, $menteeUserId);
        $update = PrepBoardUpdate::query()
            ->where('prep_board_id', $board->id)
            ->where('id', $updateId)
            ->first();

        if (!$update) {
            return response()->json(['errors' => [['message' => 'Update not found']]], 404);
        }

        $update = PrepBoardLogic::replyToUpdate(
            $update,
            (int) $request->user()->id,
            (string) $request->input('reply_body')
        );

        PrepBoardLogic::notifyMenteeReply($board);

        return response()->json([
            'message' => 'Reply sent.',
            'update' => PrepBoardLogic::formatUpdate($update),
            'board' => PrepBoardLogic::formatBoard($board->fresh()),
        ]);
    }
}
