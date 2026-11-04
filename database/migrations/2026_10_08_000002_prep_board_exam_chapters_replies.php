<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prep_boards', function (Blueprint $table) {
            if (!Schema::hasColumn('prep_boards', 'exam_category')) {
                $table->string('exam_category', 32)->nullable()->after('status');
            }
        });

        Schema::table('prep_board_subjects', function (Blueprint $table) {
            if (!Schema::hasColumn('prep_board_subjects', 'chapters')) {
                $table->json('chapters')->nullable()->after('subject_name');
            }
        });

        Schema::table('prep_board_updates', function (Blueprint $table) {
            if (!Schema::hasColumn('prep_board_updates', 'reply_body')) {
                $table->text('reply_body')->nullable()->after('body');
            }
            if (!Schema::hasColumn('prep_board_updates', 'replied_at')) {
                $table->timestamp('replied_at')->nullable()->after('reply_body');
            }
            if (!Schema::hasColumn('prep_board_updates', 'replied_by_user_id')) {
                $table->unsignedBigInteger('replied_by_user_id')->nullable()->after('replied_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('prep_boards', function (Blueprint $table) {
            if (Schema::hasColumn('prep_boards', 'exam_category')) {
                $table->dropColumn('exam_category');
            }
        });

        Schema::table('prep_board_subjects', function (Blueprint $table) {
            if (Schema::hasColumn('prep_board_subjects', 'chapters')) {
                $table->dropColumn('chapters');
            }
        });

        Schema::table('prep_board_updates', function (Blueprint $table) {
            foreach (['reply_body', 'replied_at', 'replied_by_user_id'] as $col) {
                if (Schema::hasColumn('prep_board_updates', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
