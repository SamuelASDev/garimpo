<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Descrição completa colada pelo próprio usuário quando o site de origem só mostra um resumo.
 * Vale só para quem colou (não altera a vaga para os outros).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pasted_descriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            $table->mediumText('description');
            $table->timestamps();
            $table->unique(['user_id', 'job_posting_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pasted_descriptions');
    }
};
