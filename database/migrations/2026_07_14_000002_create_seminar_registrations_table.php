<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSeminarRegistrationsTable extends Migration
{
    public function up(): void
    {
        Schema::create('seminar_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seminar_id')->constrained('seminars')->cascadeOnDelete();
            $table->string('registration_id', 64)->unique();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 32);
            $table->string('college')->nullable();
            $table->text('details')->nullable();
            $table->string('source', 64)->default('mentorkhoj_web');
            $table->string('status', 32)->default('pending');
            $table->timestamps();

            $table->unique(['seminar_id', 'email']);
            $table->index(['seminar_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seminar_registrations');
    }
}
