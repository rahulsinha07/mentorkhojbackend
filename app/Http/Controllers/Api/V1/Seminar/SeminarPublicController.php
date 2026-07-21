<?php

namespace App\Http\Controllers\Api\V1\Seminar;

use App\CentralLogics\FormMailLogic;
use App\CentralLogics\Helpers;
use App\CentralLogics\SeminarLogic;
use App\Http\Controllers\Controller;
use App\Model\Seminar\Seminar;
use App\Model\Seminar\SeminarRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SeminarPublicController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Seminar::published()->orderBy('sort_order')->orderByDesc('id');

        $limit = (int) ($request->limit ?? 50);
        $offset = (int) ($request->offset ?? 0);

        $total = $query->count();
        $seminars = $query->skip($offset)->take($limit)->get()
            ->map(fn ($s) => SeminarLogic::formatPublic($s));

        return response()->json([
            'total_size' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'seminars' => $seminars,
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $seminar = SeminarLogic::resolveBySlug($slug);
        if (!$seminar || !$seminar->is_published || $seminar->status === 'draft') {
            return response()->json(['errors' => [['code' => 'not_found', 'message' => 'Seminar not found']]], 404);
        }

        return response()->json(SeminarLogic::formatPublic($seminar));
    }

    public function register(Request $request, string $slug): JsonResponse
    {
        if (!$request->user()) {
            return response()->json([
                'ok' => false,
                'message' => 'Login required to book this seminar.',
            ], 401);
        }

        $seminar = SeminarLogic::resolveBySlug($slug);
        if (!$seminar || !$seminar->is_published || $seminar->status === 'draft') {
            return response()->json(['errors' => [['code' => 'not_found', 'message' => 'Seminar not found']]], 404);
        }

        if ((float) ($seminar->fee_amount ?? 0) > 0) {
            return response()->json([
                'ok' => false,
                'message' => 'This is a paid seminar. Please book via the website to complete payment.',
            ], 403);
        }

        if ($seminar->status === 'paused') {
            return response()->json([
                'ok' => false,
                'message' => 'Registration for this seminar is currently paused.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => ['required', 'string', 'max:32', 'regex:/^(\+91[6-9]\d{9}|\+[1-9]\d{9,14}|[6-9]\d{9})$/'],
            'org' => 'required|string|max:255',
            'details' => 'nullable|string|max:2000',
            'seminar_slug' => 'nullable|string|max:120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $email = strtolower(trim($request->email));
        $existing = SeminarRegistration::where('seminar_id', $seminar->id)
            ->where('email', $email)
            ->first();

        if ($existing) {
            return response()->json([
                'ok' => false,
                'already_booked' => true,
                'message' => 'You have already registered for this seminar with this email.',
            ], 409);
        }

        $registration = SeminarRegistration::create([
            'seminar_id' => $seminar->id,
            'registration_id' => SeminarLogic::generateRegistrationId(),
            'name' => trim($request->name),
            'email' => $email,
            'phone' => trim($request->phone),
            'college' => trim($request->org),
            'details' => trim((string) $request->details) ?: null,
            'source' => $request->input('appMeta.source', 'mentorkhoj_web'),
            'status' => 'pending',
        ]);

        $emailSent = FormMailLogic::sendSeminarRegistrationEmails($seminar, $registration);
        $adminEmail = FormMailLogic::adminEmail();

        return response()->json([
            'ok' => true,
            'id' => $registration->registration_id,
            'message' => $emailSent
                ? 'Thank you for registering. A confirmation email has been sent to you.'
                : 'Thank you for registering. We will confirm your booking shortly.',
            'email_sent' => $emailSent,
            'sheet_synced' => true,
            'email_message' => $emailSent
                ? "A confirmation email with full seminar details has been sent to {$registration->email}. Please check your inbox and spam folder."
                : "Your registration was saved. If you do not receive a confirmation email within a few minutes, write to {$adminEmail}.",
        ]);
    }
}
