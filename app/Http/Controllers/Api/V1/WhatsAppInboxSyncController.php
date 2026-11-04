<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\WhatsAppCloudApi;
use App\Http\Controllers\Controller;
use App\Model\WhatsApp\WhatsAppMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bidirectional inbox sync for mentorkhoj-whatsapp-sender ↔ Laravel.
 * Auth: X-Inbox-Sync-Secret (env WHATSAPP_INBOX_SYNC_SECRET or business_settings).
 */
class WhatsAppInboxSyncController extends Controller
{
    public function ingest(Request $request): JsonResponse
    {
        if (!$this->authorized($request)) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        if (!Schema::hasTable('whatsapp_messages')) {
            return response()->json(['ok' => false, 'error' => 'whatsapp_messages missing'], 503);
        }

        $data = $request->validate([
            'messages' => 'required|array|min:1|max:500',
            'messages.*.wa_id' => 'required|string|max:32',
            'messages.*.direction' => 'required|in:in,out,inbound,outbound',
            'messages.*.body' => 'nullable|string|max:8192',
            'messages.*.type' => 'nullable|string|max:32',
            'messages.*.status' => 'nullable|string|max:24',
            'messages.*.source' => 'nullable|string|max:32',
            'messages.*.wamid' => 'nullable|string|max:128',
            'messages.*.contact_name' => 'nullable|string|max:120',
            'messages.*.occurred_at' => 'nullable|date',
            'messages.*.payload' => 'nullable|array',
        ]);

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($data['messages'] as $row) {
            $waId = WhatsAppCloudApi::normalizeWaId((string) $row['wa_id']);
            if ($waId === '') {
                $skipped++;
                continue;
            }

            $direction = in_array($row['direction'], ['out', 'outbound'], true) ? 'out' : 'in';
            $wamid = trim((string) ($row['wamid'] ?? ''));
            $occurred = !empty($row['occurred_at'])
                ? \Carbon\Carbon::parse($row['occurred_at'])
                : now();

            $attrs = [
                'wa_id' => $waId,
                'contact_name' => $row['contact_name'] ?? null,
                'direction' => $direction,
                'type' => (string) ($row['type'] ?? 'text'),
                'body' => (string) ($row['body'] ?? ''),
                'status' => (string) ($row['status'] ?? ($direction === 'in' ? 'received' : 'sent')),
                'source' => (string) ($row['source'] ?? 'saas'),
                'payload' => $row['payload'] ?? null,
                'occurred_at' => $occurred,
            ];

            try {
                if ($wamid !== '') {
                    $existing = WhatsAppMessage::query()->where('wamid', $wamid)->first();
                    if ($existing) {
                        // Prefer non-empty name/body; never wipe source=webhook with weaker data.
                        $existing->fill(array_filter([
                            'contact_name' => $attrs['contact_name'] ?: $existing->contact_name,
                            'body' => $attrs['body'] !== '' ? $attrs['body'] : $existing->body,
                            'status' => $attrs['status'] ?: $existing->status,
                            'type' => $attrs['type'] ?: $existing->type,
                        ], static fn ($v) => $v !== null && $v !== ''));
                        if ($existing->isDirty()) {
                            $existing->save();
                            $updated++;
                        } else {
                            $skipped++;
                        }
                        continue;
                    }
                    WhatsAppMessage::query()->create($attrs + ['wamid' => $wamid]);
                    $created++;
                    continue;
                }

                WhatsAppMessage::query()->create($attrs + ['wamid' => null]);
                $created++;
            } catch (\Throwable $e) {
                $skipped++;
            }
        }

        return response()->json([
            'ok' => true,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }

    public function export(Request $request): JsonResponse
    {
        if (!$this->authorized($request)) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        if (!Schema::hasTable('whatsapp_messages')) {
            return response()->json(['ok' => false, 'messages' => [], 'threads' => [], 'stats' => []]);
        }

        $afterId = (int) $request->query('after_id', 0);
        $waId = WhatsAppCloudApi::normalizeWaId((string) $request->query('wa_id', ''));
        $limit = min(1000, max(1, (int) $request->query('limit', 500)));

        $query = WhatsAppMessage::query()->orderBy('id');
        if ($afterId > 0) {
            $query->where('id', '>', $afterId);
        }
        if ($waId !== '') {
            $query->where('wa_id', $waId);
        }
        $messages = $query->limit($limit)->get()->map(static function (WhatsAppMessage $m) {
            return [
                'id' => $m->id,
                'wa_id' => $m->wa_id,
                'contact_name' => $m->contact_name,
                'direction' => $m->direction,
                'wamid' => $m->wamid,
                'type' => $m->type,
                'body' => (string) $m->body,
                'status' => $m->status,
                'source' => $m->source,
                'occurred_at' => optional($m->occurred_at)->toIso8601String(),
            ];
        })->values();

        $latestIds = WhatsAppMessage::query()
            ->selectRaw('MAX(id) as id')
            ->groupBy('wa_id')
            ->pluck('id');

        $threads = WhatsAppMessage::query()
            ->whereIn('id', $latestIds)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(static function (WhatsAppMessage $m) {
                return [
                    'wa_id' => $m->wa_id,
                    'contact_name' => $m->contact_name,
                    'body' => mb_substr((string) $m->body, 0, 120),
                    'direction' => $m->direction,
                    'occurred_at' => optional($m->occurred_at)->toIso8601String(),
                    'id' => $m->id,
                ];
            })
            ->values();

        $maxId = (int) (WhatsAppMessage::query()->max('id') ?? 0);

        return response()->json([
            'ok' => true,
            'after_id' => $afterId,
            'max_id' => $maxId,
            'messages' => $messages,
            'threads' => $threads,
            'stats' => [
                'total' => (int) WhatsAppMessage::query()->count(),
                'inbound' => (int) WhatsAppMessage::query()->where('direction', 'in')->count(),
                'outbound' => (int) WhatsAppMessage::query()->where('direction', 'out')->count(),
                'conversations' => $threads->count(),
            ],
        ]);
    }

    private function authorized(Request $request): bool
    {
        $provided = (string) (
            $request->header('X-Inbox-Sync-Secret')
            ?: $request->query('sync_secret', '')
        );
        if ($provided === '') {
            return false;
        }

        $expected = trim((string) env('WHATSAPP_INBOX_SYNC_SECRET', ''));
        if ($expected === '') {
            $row = DB::table('business_settings')->where('key', 'whatsapp_messaging')->first();
            $settings = $row && $row->value ? json_decode($row->value, true) : [];
            $expected = is_array($settings)
                ? trim((string) ($settings['inbox_sync_secret'] ?? ''))
                : '';
        }

        return $expected !== '' && hash_equals($expected, $provided);
    }
}
