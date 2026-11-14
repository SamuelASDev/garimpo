<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LGPD: registro de quando a pessoa aceitou os termos/política e se autorizou o uso da IA grátis
 * (envio do currículo, sem contatos, a provedores de IA fora do Brasil).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('terms_accepted_at')->nullable()->after('remember_token');
            $table->timestamp('ai_consent_at')->nullable()->after('terms_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['terms_accepted_at', 'ai_consent_at']);
        });
    }
};
