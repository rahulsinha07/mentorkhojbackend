<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWhatsappMessagesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('whatsapp_messages')) {
            return;
        }

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->string('wa_id', 32)->index();
            $table->string('contact_name', 120)->nullable();
            $table->string('direction', 8);
            $table->string('wamid', 128)->nullable()->unique();
            $table->string('type', 32)->default('text');
            $table->text('body')->nullable();
            $table->string('status', 24)->nullable();
            $table->string('source', 32)->default('webhook');
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->timestamps();

            $table->index(['wa_id', 'id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_messages');
    }
}
