<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive: target cycles + daily targets + nullable target_progress on updates.
 * Does not alter existing PrepBoard plan / free-text update behavior.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('prep_board_target_cycles')) {
            Schema::create('prep_board_target_cycles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('prep_board_id');
                $table->date('start_date');
                $table->json('days')->nullable();
                $table->string('status', 16)->default('draft'); // draft | published
                $table->timestamp('published_at')->nullable();
                $table->timestamp('publish_notified_at')->nullable();
                $table->unsignedBigInteger('assigned_by_user_id')->nullable();
                $table->timestamps();

                $table->unique(['prep_board_id', 'start_date'], 'prep_board_cycles_board_start_uq');
                $table->foreign('prep_board_id')
                    ->references('id')
                    ->on('prep_boards')
                    ->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('prep_board_daily_targets')) {
            Schema::create('prep_board_daily_targets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('prep_board_id');
                $table->date('target_date');
                $table->unsignedBigInteger('cycle_id')->nullable();
                $table->unsignedTinyInteger('day_offset')->nullable(); // 0..6
                $table->json('items')->nullable();
                $table->string('source', 24)->default('manual'); // manual | cycle
                $table->unsignedBigInteger('assigned_by_user_id')->nullable();
                $table->timestamps();

                $table->unique(['prep_board_id', 'target_date'], 'prep_board_targets_board_date_uq');
                $table->foreign('prep_board_id')
                    ->references('id')
                    ->on('prep_boards')
                    ->onDelete('cascade');
                $table->foreign('cycle_id')
                    ->references('id')
                    ->on('prep_board_target_cycles')
                    ->onDelete('set null');
            });
        }

        Schema::table('prep_board_updates', function (Blueprint $table) {
            if (!Schema::hasColumn('prep_board_updates', 'target_progress')) {
                $table->json('target_progress')->nullable()->after('body');
            }
        });
    }

    public function down(): void
    {
        Schema::table('prep_board_updates', function (Blueprint $table) {
            if (Schema::hasColumn('prep_board_updates', 'target_progress')) {
                $table->dropColumn('target_progress');
            }
        });

        Schema::dropIfExists('prep_board_daily_targets');
        Schema::dropIfExists('prep_board_target_cycles');
    }
};
