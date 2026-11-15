<?php

namespace Tests\Feature;

use App\Mcp\Servers\GarimpoServer;
use App\Mcp\Tools\GetResume;
use App\Mcp\Tools\ListResumes;
use App\Models\JobPosting;
use App\Models\ResumeTailoring;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResumeAssistantTest extends TestCase
{
    use RefreshDatabase;

    private function fakeAi(array $data): void
    {
        config(['services.groq.key' => 'teste', 'services.gemini.key' => null, 'services.openrouter.key' => null]);
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode($data)]]]])]);
    }

    /** @return array{0: User, 1: JobPosting} */
    private function userWithTailoring(): array
    {
        $user = User::factory()->create();
        $user->resume()->create(['html' => '<h1>Fulano</h1><p>PHP</p>']);
        $job = JobPosting::create(['source' => 'gupy', 'url' => 'https://x.test/1', 'fingerprint' => 'a', 'title' => 'Dev PHP Pleno', 'company' => 'Acme', 'techs' => ['PHP', 'Docker']]);
        ResumeTailoring::create(['user_id' => $user->id, 'job_posting_id' => $job->id, 'content' => '<p>PHP <mark>ADICIONE: Docker</mark></p>', 'generated_by' => 'groq']);

        return [$user, $job];
    }

    public function test_job_resume_suggestions_bring_rewritten_text_and_consider_pages(): void
    {
        [$user, $job] = $this->userWithTailoring();
        $this->fakeAi(['recomendacoes' => [
            ['tipo' => 'ajustar', 'trecho' => 'Cargo alvo: Desenvolvedor Júnior', 'novo' => 'Cargo alvo: Analista de Suporte Júnior', 'motivo' => 'A vaga é de suporte.'],
            ['tipo' => 'remover', 'trecho' => 'Photoshop', 'motivo' => 'Não ajuda nesta vaga.'],
        ]]);

        $this->actingAs($user)->postJson(route('jobs.resume.suggestions', $job), ['pages' => 2])->assertOk()
            ->assertJsonPath('recommendations.0.novo', 'Cargo alvo: Analista de Suporte Júnior')
            ->assertJsonPath('recommendations.1.novo', null);

        $this->assertCount(2, ResumeTailoring::first()->recommendations);
        Http::assertSent(fn ($request) => str_contains($request['messages'][0]['content'], 'está ocupando 2 páginas')
            && str_contains($request['messages'][0]['content'], 'Revise também a ESCRITA'));
    }

    public function test_resumes_page_lists_main_and_job_resumes(): void
    {
        [$user] = $this->userWithTailoring();

        $this->actingAs($user)->get(route('resumes.index'))->assertOk()
            ->assertSee('Currículo principal')->assertSee('Dev PHP Pleno')->assertSee('50%')
            ->assertDontSee('data-resume-editor', false); // a lista não carrega editor
    }

    public function test_chat_edits_document_and_sanitizes_ai_html(): void
    {
        [$user, $job] = $this->userWithTailoring();
        $this->fakeAi(['resposta' => 'Adicionei o Docker.', 'html' => '<h1 onclick="x()">Fulano</h1><script>alert(1)</script><p style="text-align: center; color: red">PHP, Docker</p><a href="javascript:x">link</a>']);

        $this->actingAs($user)->postJson(route('jobs.resume.assistant', $job), ['message' => 'Adicione que usei Docker na Acme', 'html' => '<p>PHP</p>'])
            ->assertOk()
            ->assertJsonPath('reply', 'Adicionei o Docker.')
            ->assertJsonPath('html', '<h1>Fulano</h1><p style="text-align: center">PHP, Docker</p>link');
    }

    public function test_chat_question_returns_only_answer(): void
    {
        [$user] = $this->userWithTailoring();
        $this->fakeAi(['resposta' => '1. Quantifique resultados.', 'html' => null]);

        $this->actingAs($user)->postJson(route('resume.assistant'), ['message' => 'Que melhorias você sugere?', 'html' => '<p>PHP</p>'])
            ->assertOk()->assertJsonPath('html', null)->assertJsonPath('reply', '1. Quantifique resultados.');
    }

    public function test_chat_on_someone_elses_job_resume_is_not_found(): void
    {
        [, $job] = $this->userWithTailoring();

        $this->actingAs(User::factory()->create())
            ->postJson(route('jobs.resume.assistant', $job), ['message' => 'oi', 'html' => '<p>x</p>'])->assertNotFound();
    }

    public function test_suggestions_are_saved_on_main_resume(): void
    {
        [$user] = $this->userWithTailoring();
        $this->fakeAi(['recomendacoes' => [['tipo' => 'ajustar', 'trecho' => 'Quantifique resultados', 'motivo' => 'Recrutadores valorizam números']]]);

        $this->actingAs($user)->postJson(route('resume.suggestions'))->assertOk()->assertJsonPath('recommendations.0.tipo', 'ajustar');
        $this->assertSame('Quantifique resultados', $user->fresh()->resume->recommendations[0]['trecho']);
    }

    public function test_deleting_job_resume_keeps_main_resume(): void
    {
        [$user, $job] = $this->userWithTailoring();

        $this->actingAs($user)->delete(route('jobs.resume.destroy', $job))->assertRedirect(route('resumes.index'));
        $this->assertSame(0, ResumeTailoring::count());
        $this->assertNotNull($user->fresh()->resume);
    }

    public function test_mcp_lists_and_reads_job_resume(): void
    {
        [$user, $job] = $this->userWithTailoring();

        GarimpoServer::actingAs($user)->tool(ListResumes::class)->assertOk()->assertSee('Dev PHP Pleno');
        GarimpoServer::actingAs($user)->tool(GetResume::class, ['job_id' => $job->id])->assertOk()->assertSee('PHP')->assertDontSee('ADICIONE');
    }
}
