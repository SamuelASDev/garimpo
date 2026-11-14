<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O currículo passa a ser um documento do editor (HTML). Os campos antigos ficam opcionais.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->longText('html')->nullable()->after('user_id');
            $table->string('full_name')->nullable()->change();
        });

        // Sugestões da IA para o currículo da vaga (remover / adicionar / ajustar).
        Schema::table('resume_tailorings', function (Blueprint $table) {
            $table->json('recommendations')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->dropColumn('html');
        });
        Schema::table('resume_tailorings', function (Blueprint $table) {
            $table->dropColumn('recommendations');
        });
    }
};
