<?php

use App\Services\ResumeTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrige currículos por vaga em que a IA trocou telefone/e-mail/links por "ADICIONE: ..."
 * (ela confundia os marcadores de privacidade com dado faltando): recoloca o cabeçalho do currículo principal.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('resume_tailorings')
            ->join('resumes', 'resumes.user_id', '=', 'resume_tailorings.user_id')
            ->select(['resume_tailorings.id', 'resume_tailorings.content', 'resumes.html'])
            ->orderBy('resume_tailorings.id')
            ->chunk(200, function ($rows) {
                foreach ($rows as $row) {
                    $fixed = ResumeTemplate::withHeaderFrom((string) $row->content, $row->html);
                    if ($fixed !== $row->content) {
                        DB::table('resume_tailorings')->where('id', $row->id)->update(['content' => $fixed]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Correção de dados: não há o que desfazer.
    }
};
