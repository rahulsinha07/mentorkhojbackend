<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSessionChatMessagesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('session_chat_messages')) {
            return;
        }

        Schema::create('session_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mentee_user_id');
            $table->unsignedBigInteger('mentor_id');
            $table->unsignedBigInteger('demo_booking_id')->nullable();
            $table->unsignedBigInteger('mentor_booking_id')->nullable();
            $table->string('sender_role', 16);
            $table->text('body');
            $table->timestamps();

            $table->index(['mentee_user_id', 'mentor_id', 'id'], 'session_chat_thread_idx');
            $table->index(['mentor_id', 'mentee_user_id'], 'session_chat_mentor_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('session_chat_messages');
    }
}
