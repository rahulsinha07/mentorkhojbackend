<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAttachmentToSessionChatMessages extends Migration
{
    public function up()
    {
        Schema::table('session_chat_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('session_chat_messages', 'attachment_path')) {
                $table->string('attachment_path')->nullable()->after('body');
                $table->string('attachment_original_name')->nullable()->after('attachment_path');
                $table->string('attachment_mime', 127)->nullable()->after('attachment_original_name');
                $table->unsignedInteger('attachment_size')->nullable()->after('attachment_mime');
            }
        });
    }

    public function down()
    {
        Schema::table('session_chat_messages', function (Blueprint $table) {
            $table->dropColumn([
                'attachment_path',
                'attachment_original_name',
                'attachment_mime',
                'attachment_size',
            ]);
        });
    }
}
