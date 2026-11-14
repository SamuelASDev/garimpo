<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Competências técnicas (hardware, redes, atendimento...) e comportamentais pedidas na vaga.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->json('skills')->nullable()->after('techs');
            $table->json('soft_skills')->nullable()->after('skills');
        });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropColumn(['skills', 'soft_skills']);
        });
    }
};
