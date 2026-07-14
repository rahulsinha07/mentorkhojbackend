<?php

namespace App\Http\Controllers\Api\V1\Internship;

use App\CentralLogics\FormMailLogic;
use App\CentralLogics\Helpers;
use App\CentralLogics\InternshipLogic;
use App\Http\Controllers\Controller;
use App\Model\Internship\Internship;
use App\Model\Internship\InternshipApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InternshipPublicController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Internship::published()->orderBy('sort_order')->orderByDesc('id');

        $limit = (int) ($request->limit ?? 50);
        $offset = (int) ($request->offset ?? 0);

        $total = $query->count();
        $internships = $query->skip($offset)->take($limit)->get()
            ->map(fn ($i) => InternshipLogic::formatPublic($i));

        return response()->json([
            'total_size' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'internships' => $internships,
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $internship = InternshipLogic::resolveBySlug($slug);
        if (!$internship || !$internship->is_published || $internship->status === 'draft') {
            return response()->json(['errors' => [['code' => 'not_found', 'message' => 'Internship not found']]], 404);
        }

        return response()->json(InternshipLogic::formatPublic($internship));
    }

    public function apply(Request $request, ?string $slug = null): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => ['required', 'string', 'max:32', 'regex:/^(\+91[6-9]\d{9}|\+[1-9]\d{9,14}|[6-9]\d{9})$/'],
            'org' => 'required|string|max:255',
            'role' => 'required|string|max:255',
            'resume_url' => 'nullable|url|max:500',
            'message' => 'nullable|string|max:5000',
            'internship_slug' => 'nullable|string|max:120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $slug = $slug ?: $request->input('internship_slug');
        $internship = null;
        if ($slug) {
            $internship = InternshipLogic::resolveBySlug($slug);
        }
        if (!$internship) {
            $internship = InternshipLogic::resolveByRole(trim($request->role));
        }

        if ($internship && ($internship->status === 'paused' || !$internship->is_published)) {
            return response()->json([
                'ok' => false,
                'message' => 'Applications for this role are currently closed.',
            ], 403);
        }

        $application = InternshipApplication::create([
            'internship_id' => $internship?->id,
            'application_id' => InternshipLogic::generateApplicationId(),
            'name' => trim($request->name),
            'email' => strtolower(trim($request->email)),
            'phone' => trim($request->phone),
            'org' => trim($request->org),
            'role' => trim($request->role),
            'resume_url' => trim((string) $request->resume_url) ?: null,
            'message' => trim((string) $request->message) ?: null,
            'source' => $request->input('appMeta.source', 'mentorkhoj_web'),
            'status' => 'pending',
        ]);

        $emailSent = FormMailLogic::sendInternshipApplicationEmails($internship, $application);
        $adminEmail = FormMailLogic::adminEmail();

        return response()->json([
            'ok' => true,
            'id' => $application->application_id,
            'message' => $emailSent
                ? 'Thank you for applying! A confirmation email has been sent to you.'
                : 'Thank you for applying! Our team will review your application shortly.',
            'email_sent' => $emailSent,
            'email_message' => $emailSent
                ? "A confirmation email with your application and role details has been sent to {$application->email}. Please check your inbox and spam folder."
                : "Your application was saved. If you do not receive a confirmation email within a few minutes, write to {$adminEmail}.",
        ]);
    }
}
