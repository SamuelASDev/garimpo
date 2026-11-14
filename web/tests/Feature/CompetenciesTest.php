<?php

namespace Tests\Feature;

use App\Models\JobPosting;
use App\Models\Resume;
use App\Services\AtsMatcher;
use App\Services\JobClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetenciesTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_job_competencies_beyond_languages(): void
    {
        $text = file_get_contents(base_path('tests/Fixtures/vaga-suporte.txt'));
        $result = app(JobClassifier::class)->classify('Técnico de Suporte Jr', $text);

        $this->assertSame('junior', $result['seniority']);
        $this->assertSame('suporte', $result['area']);
        foreach (['Hardware', 'Montagem e manutenção de computadores', 'Nobreak', 'Cabeamento estruturado', 'Redes locais', 'Atendimento ao usuário', 'Ensino técnico'] as $skill) {
            $this->assertContains($skill, $result['skills']);
        }
        $this->assertEqualsCanonicalizing(['Proatividade', 'Relacionamento interpessoal', 'Agilidade', 'Resolução de problemas'], $result['soft_skills']);
    }

    public function test_developer_resume_shows_what_is_missing_for_support_job(): void
    {
        $text = file_get_contents(base_path('tests/Fixtures/vaga-suporte.txt'));
        $job = JobPosting::create(['source' => 'infojobs', 'url' => 'https://x.test/1', 'fingerprint' => 'a', 'title' => 'Técnico de Suporte Jr',
            ...app(JobClassifier::class)->classify('Técnico de Suporte Jr', $text)]);
        $resume = new Resume(['html' => '<p>Desenvolvedor PHP e Laravel. Linux e Windows no dia a dia. Atendimento aos usuários do sistema. Perfil proativo.</p>']);

        $match = app(AtsMatcher::class)->match($resume, $job);

        $this->assertContains('Linux', $match['matched']);
        $this->assertContains('Atendimento ao usuário', $match['matched']);
        $this->assertContains('Nobreak', $match['missing']);
        $this->assertContains('Montagem e manutenção de computadores', $match['missing']);
        $this->assertSame(['Proatividade'], $match['soft_matched']);
        $this->assertContains('Relacionamento interpessoal', $match['soft_missing']);
        $this->assertGreaterThan(0, $match['score']);
        $this->assertLessThan(50, $match['score']);
    }
}
