<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Variações do currículo montadas pela pessoa (ex.: "Back-end PHP", "Suporte"), sem depender de uma vaga. */
    public function up(): void
    {
        Schema::create('resume_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('category', 40)->nullable();
            $table->string('description', 300)->nullable();
            $table->longText('html');
            $table->json('recommendations')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resume_variants');
    }
};
