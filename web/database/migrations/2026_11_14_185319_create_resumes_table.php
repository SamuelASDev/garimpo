<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('headline')->nullable();   // ex.: Desenvolvedor PHP/Laravel
            $table->string('contact')->nullable();    // email · telefone · LinkedIn · GitHub
            $table->text('summary')->nullable();
            $table->text('skills')->nullable();       // separado por vírgula
            $table->text('experience')->nullable();
            $table->text('education')->nullable();
            $table->text('extras')->nullable();       // cursos, certificações, idiomas
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resumes');
    }
};
