<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStudentSectionOverridesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('student_section_overrides')) {
            return;
        }

        Schema::create('student_section_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('phone_digits', 32)->nullable()->unique();
            $table->string('email')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('section', 16)->default('other'); // neet|jee|tech|ai|other
            $table->text('message_template')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('student_section_overrides');
    }
}
