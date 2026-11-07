<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddZohoRecordingColumnsToMentorBookingsTable extends Migration
{
    public function up(): void
    {
        Schema::table('mentor_bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('mentor_bookings', 'zoho_recording_fetch_status')) {
                // null = not due yet (session hasn't happened / Zoho needs time to process),
                // pending = due and queued, found = recording attached, not_found = Zoho has
                // no recording for this meeting (e.g. recording wasn't enabled), error = API failure.
                $table->string('zoho_recording_fetch_status', 20)->nullable()->after('zoho_last_error');
            }
            if (!Schema::hasColumn('mentor_bookings', 'zoho_recording_fetch_attempts')) {
                $table->unsignedTinyInteger('zoho_recording_fetch_attempts')->default(0)->after('zoho_recording_fetch_status');
            }
            if (!Schema::hasColumn('mentor_bookings', 'recording_play_url')) {
                $table->string('recording_play_url', 1024)->nullable()->after('zoho_recording_fetch_attempts');
            }
            if (!Schema::hasColumn('mentor_bookings', 'recording_download_url')) {
                $table->string('recording_download_url', 1024)->nullable()->after('recording_play_url');
            }
            if (!Schema::hasColumn('mentor_bookings', 'recording_duration_seconds')) {
                $table->unsignedInteger('recording_duration_seconds')->nullable()->after('recording_download_url');
            }
            if (!Schema::hasColumn('mentor_bookings', 'transcript_text')) {
                $table->longText('transcript_text')->nullable()->after('recording_duration_seconds');
            }
            if (!Schema::hasColumn('mentor_bookings', 'transcript_language')) {
                // Zoho's own detected/transcribed language (ISO 639-1), e.g. "en".
                $table->string('transcript_language', 10)->nullable()->after('transcript_text');
            }
            if (!Schema::hasColumn('mentor_bookings', 'summary_text')) {
                $table->longText('summary_text')->nullable()->after('transcript_language');
            }
            if (!Schema::hasColumn('mentor_bookings', 'translated_transcripts')) {
                // JSON map of {"<iso-lang-code>": "<translated text>"}, filled on demand
                // (see MeetingTranslationService) so we never pay for a translation nobody asked for.
                $table->json('translated_transcripts')->nullable()->after('summary_text');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mentor_bookings', function (Blueprint $table) {
            $table->dropColumn([
                'zoho_recording_fetch_status',
                'zoho_recording_fetch_attempts',
                'recording_play_url',
                'recording_download_url',
                'recording_duration_seconds',
                'transcript_text',
                'transcript_language',
                'summary_text',
                'translated_transcripts',
            ]);
        });
    }
}
