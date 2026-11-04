<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prep_boards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mentor_id')->index();
            $table->unsignedBigInteger('mentee_user_id')->index();
            $table->string('status', 16)->default('draft')->index();
            $table->timestamp('mentor_last_edited_at')->nullable();
            $table->timestamp('plan_ready_notified_at')->nullable();
            $table->timestamp('last_update_notified_at')->nullable();
            $table->timestamps();

            $table->unique(['mentor_id', 'mentee_user_id']);
        });

        Schema::create('prep_board_subjects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('prep_board_id')->index();
            $table->string('slot', 16)->index(); // revision | current
            $table->string('subject_name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('theory_text')->nullable();
            $table->text('practice_text')->nullable();
            $table->text('note')->nullable();
            $table->decimal('hours_devoted', 8, 2)->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('prep_board_id')
                ->references('id')
                ->on('prep_boards')
                ->onDelete('cascade');
        });

        Schema::create('prep_board_updates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('prep_board_id')->index();
            $table->date('entry_date');
            $table->text('body');
            $table->unsignedBigInteger('author_user_id');
            $table->string('author_role', 16); // mentee | mentor
            $table->timestamps();

            $table->foreign('prep_board_id')
                ->references('id')
                ->on('prep_boards')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prep_board_updates');
        Schema::dropIfExists('prep_board_subjects');
        Schema::dropIfExists('prep_boards');
    }
};
