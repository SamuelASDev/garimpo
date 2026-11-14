<?php

namespace Tests\Feature;

use App\Models\JobPosting;
use App\Models\ResumeTailoring;
use App\Models\User;
use App\Services\JobDescriptionFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PastedDescriptionTest extends TestCase
{
    use RefreshDatabase;

    private function shortJob(): JobPosting
    {
        return JobPosting::create(['source' => 'vagas.com', 'url' => 'https://x.test/1', 'fingerprint' => 'a', 'title' => 'Técnico de Suporte',
            'description' => 'Resumo curto da vaga.', 'techs' => [], 'skills' => []]);
    }

    public function test_pasted_description_changes_match_only_for_who_pasted(): void
    {
        $job = $this->shortJob();
        $me = User::factory()->create();
        $me->resume()->create(['html' => '<p>Linux, montagem de computadores e atendimento aos usuários.</p>']);
        ResumeTailoring::create(['user_id' => $me->id, 'job_posting_id' => $job->id, 'content' => '<p>Linux</p>', 'generated_by' => 'copia']);

        $this->actingAs($me)->get(route('jobs.resume', $job))->assertOk()->assertSee('A descrição desta vaga veio incompleta');

        $full = str_repeat('Requisitos: Linux, Windows, montagem e manutenção de computadores, nobreak e redes locais. ', 4);
        $this->actingAs($me)->put(route('jobs.description.store', $job), ['description' => $full])->assertRedirect();

        $mine = $job->fresh()->forUser($me);
        $this->assertTrue($mine->pastedByUser);
        $this->assertContains('Nobreak', $mine->skills);
        $this->actingAs($me)->get(route('jobs.resume', $job))->assertOk()->assertSee('Usando a descrição que você colou')->assertSee('Nobreak');

        // Outra pessoa continua vendo a vaga original, e o banco não muda.
        $this->assertSame('Resumo curto da vaga.', $job->fresh()->description);
        $this->assertFalse($job->fresh()->forUser(User::factory()->create())->pastedByUser);

        $this->actingAs($me)->delete(route('jobs.description.destroy', $job))->assertRedirect();
        $this->assertFalse($job->fresh()->forUser($me)->pastedByUser);
    }

    public function test_pasted_description_must_be_complete(): void
    {
        $this->actingAs(User::factory()->create())
            ->put(route('jobs.description.store', $this->shortJob()), ['description' => 'curta demais'])
            ->assertSessionHasErrors('description');
    }

    public function test_button_and_link_texts_are_removed_from_descriptions(): void
    {
        $text = JobDescriptionFormatter::normalize("Texto real da vaga.\n\nContato\nJulia Riela\n\nReferência da Vaga\nJN-102026-7126187\n\n•\n\n• Salvar Vaga\n\n• Candidate-se\n?\n#VagaUrgente\nCompartilhar");

        $this->assertSame('Texto real da vaga.', $text);
    }

    public function test_pending_only_returns_tech_jobs_when_titles_are_sent(): void
    {
        config(['services.ingest.token' => 'segredo']);

        $this->postJson('/api/ingest/pending', ['jobs' => [
            ['url' => 'https://x.test/dev', 'title' => 'Desenvolvedor PHP'],
            ['url' => 'https://x.test/vendas', 'title' => 'Analista Comercial III'],
        ]], ['Authorization' => 'Bearer segredo'])->assertOk()->assertExactJson(['urls' => ['https://x.test/dev']]);
    }
}
