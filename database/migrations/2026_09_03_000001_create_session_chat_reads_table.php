<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSessionChatReadsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('session_chat_reads')) {
            return;
        }

        Schema::create('session_chat_reads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('mentor_id');
            $table->unsignedBigInteger('mentee_user_id');
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'mentor_id', 'mentee_user_id'], 'session_chat_reads_viewer_thread_uq');
            $table->index(['user_id'], 'session_chat_reads_user_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('session_chat_reads');
    }
}
