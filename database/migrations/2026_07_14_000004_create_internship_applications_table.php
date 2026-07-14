<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInternshipApplicationsTable extends Migration
{
    public function up(): void
    {
        Schema::create('internship_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internship_id')->nullable()->constrained('internships')->nullOnDelete();
            $table->string('application_id', 64)->unique();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 32);
            $table->string('org')->nullable();
            $table->string('role');
            $table->string('resume_url')->nullable();
            $table->text('message')->nullable();
            $table->string('source', 64)->default('mentorkhoj_web');
            $table->string('status', 32)->default('pending');
            $table->timestamps();

            $table->index(['internship_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internship_applications');
    }
}
