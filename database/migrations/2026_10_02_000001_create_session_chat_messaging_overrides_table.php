<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSessionChatMessagingOverridesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('session_chat_messaging_overrides')) {
            return;
        }

        Schema::create('session_chat_messaging_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mentee_user_id');
            $table->unsignedBigInteger('mentor_id');
            $table->string('override', 16);
            $table->timestamps();

            $table->unique(['mentee_user_id', 'mentor_id'], 'session_chat_messaging_overrides_pair_uq');
            $table->index(['mentee_user_id'], 'session_chat_messaging_overrides_mentee_idx');
            $table->index(['mentor_id'], 'session_chat_messaging_overrides_mentor_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('session_chat_messaging_overrides');
    }
}
