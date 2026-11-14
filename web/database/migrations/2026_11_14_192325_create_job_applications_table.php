<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_posting_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('company')->nullable();
            $table->string('url', 1000)->nullable();
            $table->string('status', 20)->default('enviado')->index();
            $table->date('applied_at');
            $table->date('last_contact_at')->nullable();
            $table->string('next_step')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
