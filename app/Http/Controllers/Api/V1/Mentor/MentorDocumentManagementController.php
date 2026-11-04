<?php

namespace App\Http\Controllers\Api\V1\Mentor;

use App\CentralLogics\Helpers;
use App\CentralLogics\MentorDocumentLogic;
use App\Http\Controllers\Controller;
use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorDocument;
use App\Model\Mentor\MentorDocumentFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MentorDocumentManagementController extends Controller
{
    private function mentorForUser(Request $request): ?Mentor
    {
        return Mentor::where('user_id', $request->user()->id)->first();
    }

    public static function formatDocument(MentorDocument $document): array
    {
        $document->loadMissing('files');

        return [
            'id' => $document->id,
            'mentor_id' => $document->mentor_id,
            'title' => $document->title,
            'description' => $document->description,
            'price' => $document->price,
            'is_enabled' => (bool) $document->is_enabled,
            'sort_order' => $document->sort_order,
            'total_size_bytes' => $document->total_size_bytes,
            'file_count' => $document->files->count(),
            'files' => $document->files->map(fn ($f) => [
                'id' => $f->id,
                'original_name' => $f->original_name,
                'mime' => $f->mime,
                'size' => $f->size,
                'sort_order' => $f->sort_order,
            ])->values(),
            'created_at' => $document->created_at?->toIso8601String(),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $mentor = $this->mentorForUser($request);
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        return response()->json([
            'documents' => $mentor->documents->map(fn ($d) => self::formatDocument($d))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $mentor = $this->mentorForUser($request);
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $maxOrder = $mentor->documents()->max('sort_order') ?? 0;

        $document = MentorDocument::create([
            'mentor_id' => $mentor->id,
            'title' => $request->title,
            'description' => $request->description,
            'price' => $request->price,
            'is_enabled' => true,
            'sort_order' => $maxOrder + 1,
            'total_size_bytes' => 0,
        ]);

        return response()->json([
            'message' => 'Document bundle created',
            'document' => self::formatDocument($document),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $mentor = $this->mentorForUser($request);
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $document = MentorDocument::where('mentor_id', $mentor->id)->where('id', $id)->first();
        if (!$document) {
            return response()->json(['errors' => [['message' => 'Document not found']]], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'is_enabled' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $fields = ['title', 'description', 'price', 'is_enabled', 'sort_order'];
        foreach ($fields as $field) {
            if ($request->exists($field)) {
                $value = $request->input($field);
                if ($field === 'description' && $value === '') {
                    $value = null;
                }
                if ($field === 'is_enabled') {
                    $value = (bool) $value;
                }
                $document->{$field} = $value;
            }
        }
        $document->save();

        return response()->json([
            'message' => 'Document bundle updated',
            'document' => self::formatDocument($document),
        ]);
    }

    public function toggleEnabled(Request $request, int $id): JsonResponse
    {
        $mentor = $this->mentorForUser($request);
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $document = MentorDocument::where('mentor_id', $mentor->id)->where('id', $id)->first();
        if (!$document) {
            return response()->json(['errors' => [['message' => 'Document not found']]], 404);
        }

        $document->is_enabled = (bool) $request->input('is_enabled', !$document->is_enabled);
        $document->save();

        return response()->json([
            'message' => 'Document bundle updated',
            'document' => self::formatDocument($document),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $mentor = $this->mentorForUser($request);
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $document = MentorDocument::where('mentor_id', $mentor->id)->where('id', $id)->first();
        if (!$document) {
            return response()->json(['errors' => [['message' => 'Document not found']]], 404);
        }

        if ($document->purchases()->exists()) {
            return response()->json([
                'errors' => [[
                    'message' => 'This document bundle has purchases, so it cannot be deleted. Disable it instead so it no longer appears on your public profile.',
                ]],
            ], 409);
        }

        foreach ($document->files as $file) {
            MentorDocumentLogic::deleteFile($file);
        }

        $document->delete();

        return response()->json(['message' => 'Document bundle deleted']);
    }

    public function uploadFile(Request $request, int $id): JsonResponse
    {
        $mentor = $this->mentorForUser($request);
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $document = MentorDocument::where('mentor_id', $mentor->id)->where('id', $id)->first();
        if (!$document) {
            return response()->json(['errors' => [['message' => 'Document not found']]], 404);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:' . MentorDocumentLogic::FILE_MAX_KB,
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        try {
            $file = MentorDocumentLogic::storeFile($document, $request->file('file'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['errors' => [['message' => $e->getMessage()]]], 422);
        }

        return response()->json([
            'message' => 'File uploaded',
            'document' => self::formatDocument($document->fresh()),
            'file' => [
                'id' => $file->id,
                'original_name' => $file->original_name,
                'mime' => $file->mime,
                'size' => $file->size,
            ],
        ], 201);
    }

    public function destroyFile(Request $request, int $id, int $fileId): JsonResponse
    {
        $mentor = $this->mentorForUser($request);
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $document = MentorDocument::where('mentor_id', $mentor->id)->where('id', $id)->first();
        if (!$document) {
            return response()->json(['errors' => [['message' => 'Document not found']]], 404);
        }

        $file = MentorDocumentFile::where('mentor_document_id', $document->id)->where('id', $fileId)->first();
        if (!$file) {
            return response()->json(['errors' => [['message' => 'File not found']]], 404);
        }

        MentorDocumentLogic::deleteFile($file);

        return response()->json(['message' => 'File removed']);
    }
}
