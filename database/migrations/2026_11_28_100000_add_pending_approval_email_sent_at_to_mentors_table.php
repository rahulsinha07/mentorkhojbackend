<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPendingApprovalEmailSentAtToMentorsTable extends Migration
{
    public function up(): void
    {
        Schema::table('mentors', function (Blueprint $table) {
            if (!Schema::hasColumn('mentors', 'pending_approval_email_sent_at')) {
                $table->timestamp('pending_approval_email_sent_at')->nullable()->after('welcome_email_sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mentors', function (Blueprint $table) {
            if (Schema::hasColumn('mentors', 'pending_approval_email_sent_at')) {
                $table->dropColumn('pending_approval_email_sent_at');
            }
        });
    }
}
