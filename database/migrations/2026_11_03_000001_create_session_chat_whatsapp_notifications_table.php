<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSessionChatWhatsappNotificationsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('session_chat_whatsapp_notifications')) {
            return;
        }

        Schema::create('session_chat_whatsapp_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mentee_user_id');
            $table->unsignedBigInteger('mentor_id');
            $table->string('sender_role', 16);
            $table->date('notified_date');
            $table->unsignedTinyInteger('send_count')->default(0);
            $table->timestamps();

            $table->unique(
                ['mentee_user_id', 'mentor_id', 'sender_role', 'notified_date'],
                'session_chat_whatsapp_notifications_key_uq'
            );
        });
    }

    public function down()
    {
        Schema::dropIfExists('session_chat_whatsapp_notifications');
    }
}
