<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMentorsTable extends Migration
{
    public function up(): void
    {
        Schema::create('mentors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->unique();
            $table->unsignedBigInteger('legacy_product_id')->nullable()->unique();
            $table->string('username', 100)->unique();
            $table->string('display_name');
            $table->string('headline')->nullable();
            $table->text('bio_html')->nullable();
            $table->text('images')->nullable();
            $table->text('category_ids')->nullable();
            $table->string('status', 32)->default('draft');
            $table->boolean('is_published')->default(false);
            $table->decimal('profile_discount', 8, 2)->default(0);
            $table->string('discount_type', 16)->default('percent');
            $table->unsignedInteger('view_count')->default(0);
            $table->text('share_caption')->nullable();
            $table->string('share_short_url')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['is_published', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentors');
    }
}
