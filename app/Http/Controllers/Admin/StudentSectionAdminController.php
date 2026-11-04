<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\StudentSectionLogic;
use App\CentralLogics\WhatsAppCloudApi;
use App\CentralLogics\WhatsAppDemoBookingModule;
use App\CentralLogics\WhatsAppWebLink;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Unified student list by section (demo + mentor bookings) with WhatsApp contact actions.
 * Does not modify Demo Bookings or WhatsApp Messaging pages.
 */
class StudentSectionAdminController extends Controller
{
    public function index(Request $request)
    {
        $section = strtolower(trim((string) $request->query('section', '')));
        if ($section !== '' && !isset(StudentSectionLogic::SECTIONS[$section])) {
            $section = '';
        }

        $source = strtolower(trim((string) $request->query('source', '')));
        if (!in_array($source, ['demo', 'mentor', 'both'], true)) {
            $source = '';
        }

        $q = trim((string) $request->query('q', ''));

        $result = StudentSectionLogic::listStudents(
            $section !== '' ? $section : null,
            $q !== '' ? $q : null,
            $source !== '' ? $source : null
        );

        $students = $result['students'];
        // "All" view: keep sections together (NEET block, then IIT-JEE, …).
        if ($section === '') {
            $students = StudentSectionLogic::sortGroupedBySection($students);
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 50;
        $totalFiltered = $students->count();
        $slice = $students->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $slice,
            $totalFiltered,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return view('admin-views.student-sections.index', [
            'students' => $paginator,
            'counts' => $result['counts'],
            'total_all' => $result['total'],
            'sections' => StudentSectionLogic::SECTIONS,
            'filterSection' => $section,
            'filterSource' => $source,
            'q' => $q,
            'templates' => StudentSectionLogic::loadTemplates(),
        ]);
    }

    public function updateSection(Request $request)
    {
        $data = $request->validate([
            'section' => 'required|string|in:neet,jee,tech,ai,other',
            'phone' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:190',
            'user_id' => 'nullable|integer',
        ]);

        $row = StudentSectionLogic::upsertOverride(
            $data['section'],
            $data['phone'] ?? null,
            $data['email'] ?? null,
            isset($data['user_id']) ? (int) $data['user_id'] : null
        );

        if (!$row) {
            return back()->with('error', 'Could not save section — need a phone, email, or user id.');
        }

        return back()->with('success', 'Section updated to '.StudentSectionLogic::SECTIONS[$data['section']].'.');
    }

    public function updateTemplates(Request $request)
    {
        $data = $request->validate([
            'templates' => 'required|array',
            'templates.neet' => 'nullable|string|max:4000',
            'templates.jee' => 'nullable|string|max:4000',
            'templates.tech' => 'nullable|string|max:4000',
            'templates.ai' => 'nullable|string|max:4000',
            'templates.other' => 'nullable|string|max:4000',
        ]);

        StudentSectionLogic::saveTemplates($data['templates']);

        return back()->with('success', 'Default WhatsApp message templates saved.');
    }

    public function updateDraft(Request $request)
    {
        $data = $request->validate([
            'section' => 'required|string|in:neet,jee,tech,ai,other',
            'phone' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:190',
            'user_id' => 'nullable|integer',
            'message' => 'nullable|string|max:4096',
        ]);

        $row = StudentSectionLogic::upsertOverride(
            $data['section'],
            $data['phone'] ?? null,
            $data['email'] ?? null,
            isset($data['user_id']) ? (int) $data['user_id'] : null,
            $data['message'] ?? null,
            true
        );

        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'Could not save draft.'], 422);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Build wa.me URL from editable body (JSON for AJAX).
     */
    public function whatsappLink(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string|max:32',
            'body' => 'required|string|max:4096',
        ]);

        $url = WhatsAppWebLink::url($data['phone'], $data['body']);
        if (!$url) {
            return response()->json(['ok' => false, 'message' => 'Invalid phone number.'], 422);
        }

        return response()->json(['ok' => true, 'url' => $url]);
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string|max:32',
            'body' => 'required|string|max:4096',
            'section' => 'nullable|string|in:neet,jee,tech,ai,other',
            'send_mode' => 'nullable|in:auto,text,template',
            'email' => 'nullable|email|max:190',
            'user_id' => 'nullable|integer',
            'save_draft' => 'nullable|boolean',
        ]);

        $phone = $data['phone'];
        $body = trim($data['body']);
        $section = $data['section'] ?? 'other';
        $mode = $data['send_mode'] ?? 'auto';
        $waId = WhatsAppCloudApi::normalizeWaId($phone);

        if (!empty($data['save_draft'])) {
            StudentSectionLogic::upsertOverride(
                $section,
                $phone,
                $data['email'] ?? null,
                isset($data['user_id']) ? (int) $data['user_id'] : null,
                $body,
                true
            );
        }

        $windowOpen = WhatsAppCloudApi::isCustomerWindowOpen($waId);

        if ($mode === 'text' || ($mode === 'auto' && $windowOpen)) {
            $result = WhatsAppCloudApi::sendText($phone, $body, 'student_section');
            if (($result['status'] ?? '') === 'success') {
                return back()->with('success', 'WhatsApp text sent to '.$waId.'.');
            }

            $message = $result['message'] ?? 'Send failed';
            if ($mode === 'auto' && (stripos($message, '24') !== false || stripos($message, 're-engage') !== false || stripos($message, 'outside') !== false)) {
                // Fall through to template
            } else {
                return back()->with('error', $message);
            }
        }

        // Template fallback (anytime): follow-up style with body as {{1}}, or section demo template.
        $settingsRow = DB::table('business_settings')->where('key', 'whatsapp_messaging')->first();
        $settings = $settingsRow && $settingsRow->value ? json_decode($settingsRow->value, true) : [];
        if (!is_array($settings)) {
            $settings = [];
        }

        $followup = (string) ($settings['templates']['followup'] ?? 'mentorkhoj_util_followup');
        $lastError = 'Could not send. Free text needs an open 24h window, or configure a follow-up template.';

        if ($followup !== '') {
            $result = WhatsAppCloudApi::sendTemplate($phone, $followup, [$body], 'student_section');
            if (($result['status'] ?? '') === 'success') {
                return back()->with('success', 'WhatsApp template sent (follow-up) to '.$waId.'.');
            }
            $lastError = $result['message'] ?? $lastError;
        }

        if (in_array($section, ['neet', 'jee', 'tech', 'ai'], true)) {
            $name = 'there';
            if (preg_match('/^Hi\s+([^,\n]+)/i', $body, $m)) {
                $name = trim($m[1]);
            }
            $result = WhatsAppDemoBookingModule::sendDemoBooked(
                $phone,
                $name,
                'SEC-'.strtoupper(substr(uniqid(), -6)),
                $section,
                $section,
                null,
                strtoupper($section)
            );
            if (($result['status'] ?? '') === 'success') {
                return back()->with('success', 'WhatsApp section template sent to '.$waId.'.');
            }

            return back()->with('error', $result['message'] ?? 'Template send failed.');
        }

        return back()->with('error', $lastError);
    }
}
