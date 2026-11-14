<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->string('source', 40);              // gupy, remotive, linkedin...
            $table->string('url', 1000);
            $table->string('fingerprint', 64)->unique(); // hash título+empresa+local (dedup entre fontes)
            $table->string('title');
            $table->string('company')->nullable();
            $table->string('location')->nullable();
            $table->boolean('remote')->default(false);
            $table->string('seniority', 20)->nullable()->index();
            $table->string('area', 30)->nullable()->index();
            $table->json('techs')->nullable();
            $table->text('summary')->nullable();
            $table->mediumText('description')->nullable(); // só para análise ATS, não é exibida inteira
            $table->timestamp('posted_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_postings');
    }
};
