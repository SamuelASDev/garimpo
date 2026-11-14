<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alertas de vaga: filtros salvos pelo usuário; o resumo diário junta todos num e-mail só.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->json('filters'); // q, seniority, area, tech[], remote
            $table->boolean('active')->default(true);
            $table->timestamp('last_sent_at')->nullable(); // vagas novas = criadas depois disso
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_alerts');
    }
};
