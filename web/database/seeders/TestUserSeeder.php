<?php

namespace Database\Seeders;

use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Conta de teste local com currículo de exemplo. Credenciais em TEST_USER_EMAIL / TEST_USER_PASSWORD do .env.
 */
class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('A conta de teste não deve ser criada em produção.');
        }
        $password = env('TEST_USER_PASSWORD') ?: throw new RuntimeException('Defina TEST_USER_PASSWORD no .env.');

        $user = User::updateOrCreate(
            ['email' => env('TEST_USER_EMAIL', 'teste@garimpo.test')],
            ['name' => 'Conta de Teste', 'password' => $password],
        );
        $user->forceFill(['email_verified_at' => now(), 'terms_accepted_at' => now(), 'ai_consent_at' => now()])->save();

        $user->resume()->updateOrCreate([], [
            'full_name' => 'Conta de Teste',
            'headline' => 'Desenvolvedor PHP / Laravel Júnior',
            'contact' => 'teste@garimpo.test · São Paulo - SP · github.com/exemplo',
            'summary' => 'Desenvolvedor backend com foco em PHP e Laravel, experiência com APIs REST, MySQL e Git. Busco vaga júnior ou pleno para crescer em times de produto.',
            'skills' => 'PHP, Laravel, MySQL, JavaScript, HTML, CSS, Tailwind, Git, REST, Docker (básico), Inglês intermediário',
            'experience' => "Desenvolvedor PHP — Empresa Exemplo (01/2025 – atual)\n- Criei e mantive APIs REST em Laravel consumidas por app mobile\n- Otimizei consultas MySQL, reduzindo o tempo de resposta pela metade",
            'education' => 'Análise e Desenvolvimento de Sistemas — Faculdade Exemplo (2025)',
            'extras' => 'Curso de Docker — Alura (2025)',
        ]);

        if ($job = JobPosting::where('techs', 'like', '%"PHP"%')->latest('posted_at')->first()) {
            $user->jobApplications()->firstOrCreate(
                ['job_posting_id' => $job->id],
                ['title' => $job->title, 'company' => $job->company, 'url' => $job->url, 'applied_at' => today()->subDays(17)],
            );
        }
    }
}
