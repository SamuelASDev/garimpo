<?php

namespace Tests\Feature;

use App\Models\JobPosting;
use App\Models\ResumeTailoring;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A mesma vaga vinda de outro site com o nome escrito diferente não pode entrar duas vezes,
 * e vagas parecidas mas diferentes (outro nível, outra cidade, outra descrição) não podem sumir.
 */
class JobDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    private const DESCRIPTION = 'Buscamos uma pessoa desenvolvedora para atuar no time de pagamentos, construindo APIs REST em Java '
        .'e Spring Boot, com mensageria em Kafka e banco PostgreSQL. Você vai participar do desenho da arquitetura, revisar '
        .'código com o time, escrever testes automatizados e acompanhar as entregas em produção com observabilidade. '
        .'Requisitos: experiência com Java, Spring, SQL, Git e metodologias ágeis. Diferenciais: AWS, Docker e Kubernetes.';

    private const OTHER_DESCRIPTION = 'Vaga para atuar com suporte a usuários internos, atendimento de chamados no service desk, '
        .'instalação e configuração de notebooks, impressoras e softwares corporativos, controle de inventário de equipamentos '
        .'e apoio à infraestrutura local. Requisitos: Windows, Office 365, Active Directory e redes básicas. Desejável ITIL.';

    /** @return array<string, mixed> */
    private function job(array $overrides = []): array
    {
        static $n = 0;
        $n++;

        return array_merge([
            'source' => 'gupy',
            'url' => "https://empresa.gupy.io/jobs/{$n}",
            'title' => 'Software Engineer GO PL',
            'company' => 'Stone',
            'location' => 'São Paulo - SP',
            'description' => self::DESCRIPTION,
        ], $overrides);
    }

    /** @return array<string, int> */
    private function ingest(array $jobs): array
    {
        config(['services.ingest.token' => 'segredo']);

        return $this->postJson('/api/ingest', ['jobs' => $jobs], ['Authorization' => 'Bearer segredo'])->assertOk()->json();
    }

    public function test_same_job_from_another_site_with_names_written_differently_is_not_added_again(): void
    {
        $this->ingest([$this->job(['title' => 'Senior QA', 'company' => 'BEES'])]);
        $this->ingest([$this->job(['title' => '10343 - Analyst - Desenvolvedor Fullstack Java/Angular - Híbrido', 'company' => 'Provider IT'])]);
        $this->ingest([$this->job(['title' => 'Analista Desenvolvedor Calypso', 'company' => 'Grupo Data'])]);
        $this->ingest([$this->job(['title' => 'SOFTWARE ENGINEER GO PLENO', 'company' => 'Stone'])]);
        $this->ingest([$this->job(['title' => 'Técnico de Suporte', 'company' => 'Atacadão Dia a Dia', 'location' => 'Brasília', 'description' => self::OTHER_DESCRIPTION])]);

        $stats = $this->ingest([
            $this->job(['source' => 'linkedin', 'title' => 'Senior QA', 'company' => 'BEES Brasil']),
            $this->job(['source' => 'linkedin', 'title' => 'Analyst - Desenvolvedor Fullstack Java/Angular', 'company' => 'Provider It']),
            $this->job(['source' => 'linkedin', 'title' => 'Analista Desenvolvedor(a) Calypso', 'company' => 'Data Group', 'location' => 'Brazil']),
            $this->job(['source' => 'linkedin', 'title' => 'SOFTWARE ENGINEER GO PL', 'company' => 'Stone Pagamentos S.A.']),
            $this->job(['source' => 'linkedin', 'title' => 'Técnico de Suporte', 'company' => 'Atacadão Dia a Dia - Faça parte do nosso time', 'location' => 'Brasília — DF', 'description' => self::OTHER_DESCRIPTION]),
            // O mesmo site listando de novo com o nome da empresa diferente e o mesmo texto.
            $this->job(['title' => 'Senior QA', 'company' => 'BEES Brasil', 'location' => 'Qualquer lugar']),
        ]);

        $this->assertSame(0, $stats['created']);
        $this->assertSame(6, $stats['duplicated']);
        $this->assertSame(5, JobPosting::count());
    }

    public function test_similar_but_different_jobs_are_all_kept(): void
    {
        $this->ingest([$this->job(['title' => 'Desenvolvedor Java - Pleno', 'company' => 'Brasilprev'])]);

        $stats = $this->ingest([
            // Outro nível na mesma empresa.
            $this->job(['source' => 'linkedin', 'title' => 'Desenvolvedor Java - Sênior', 'company' => 'Brasilprev']),
            // Mesmo cargo em outra cidade.
            $this->job(['source' => 'linkedin', 'title' => 'Desenvolvedor Java - Pleno', 'company' => 'Brasilprev', 'location' => 'Barueri - SP']),
            // Mesmo cargo e empresa, mas outra vaga (descrição diferente).
            $this->job(['source' => 'linkedin', 'title' => 'Desenvolvedor Java Pleno', 'company' => 'Brasilprev S.A.', 'description' => self::OTHER_DESCRIPTION]),
            // Outra empresa com o mesmo cargo e texto.
            $this->job(['source' => 'linkedin', 'title' => 'Desenvolvedor Java - Pleno', 'company' => 'Stefanini']),
            // Vaga afirmativa é um anúncio separado.
            $this->job(['source' => 'linkedin', 'title' => 'Desenvolvedor Java - Pleno | Afirmativa PCD', 'company' => 'Brasilprev']),
            // Mesmo site, mesmo cargo, texto só parecido: na dúvida fica.
            $this->job(['title' => 'Desenvolvedor Java - Pleno', 'company' => 'Brasilprev Previdência', 'description' => self::DESCRIPTION.' '.self::OTHER_DESCRIPTION.' '.self::OTHER_DESCRIPTION]),
        ]);

        $this->assertSame(6, $stats['created']);
        $this->assertSame(0, $stats['duplicated']);
    }

    public function test_different_requisition_codes_are_different_openings(): void
    {
        $this->ingest([$this->job(['title' => '12881758 - Analista de Suporte Técnico Pleno', 'company' => 'Quality Digital'])]);

        $stats = $this->ingest([$this->job(['source' => 'linkedin', 'title' => '12881999 - Analista de Suporte Técnico Pleno', 'company' => 'Quality Digital Ltda'])]);

        $this->assertSame(1, $stats['created']);
    }

    public function test_short_descriptions_are_not_enough_to_call_it_a_duplicate(): void
    {
        $this->ingest([$this->job(['title' => 'Analista de Dados', 'company' => 'Carpediem', 'description' => 'Vaga de analista de dados.'])]);

        $stats = $this->ingest([$this->job(['source' => 'linkedin', 'title' => 'Analista de Dados', 'company' => 'Carpe diem consultoria', 'description' => 'Vaga de analista de dados.'])]);

        $this->assertSame(1, $stats['created']);
    }

    public function test_command_lists_by_default_and_only_deletes_with_flag_keeping_jobs_people_use(): void
    {
        $this->ingest([$this->job(['title' => 'Senior QA', 'company' => 'BEES'])]);
        $original = JobPosting::sole();
        // Entrou antes do detector existir: grava direto, sem passar pela coleta.
        $repeated = JobPosting::create([...$original->only(['title', 'location', 'description', 'summary', 'seniority', 'area']),
            'source' => 'linkedin', 'url' => 'https://linkedin.com/jobs/1', 'fingerprint' => str_repeat('a', 64),
            'company' => 'BEES Brasil', 'company_key' => 'bees', 'posted_at' => now()]);
        $different = JobPosting::create([...$repeated->only(['title', 'location', 'company', 'company_key', 'summary', 'posted_at']),
            'source' => 'indeed', 'url' => 'https://indeed.com/1', 'fingerprint' => str_repeat('b', 64), 'description' => self::OTHER_DESCRIPTION]);
        // Alguém já gerou currículo para a repetida: ela fica e a original sai.
        ResumeTailoring::create(['user_id' => User::factory()->create()->id, 'job_posting_id' => $repeated->id, 'content' => '<p>cv</p>', 'generated_by' => 'ia']);

        $this->artisan('garimpo:deduplicar')->expectsOutputToContain('1 repetidas encontradas')->assertSuccessful();
        $this->assertSame(3, JobPosting::count());

        $this->artisan('garimpo:deduplicar --apagar')->assertSuccessful();
        $this->assertModelMissing($original);
        $this->assertModelExists($repeated);
        $this->assertModelExists($different);
    }
}
