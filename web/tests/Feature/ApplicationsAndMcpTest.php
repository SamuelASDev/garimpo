<?php

namespace Tests\Feature;

use App\Mcp\Servers\GarimpoServer;
use App\Mcp\Tools\AddApplication;
use App\Mcp\Tools\PrepareTailoredResume;
use App\Mcp\Tools\SaveTailoredResume;
use App\Mcp\Tools\SearchJobs;
use App\Mcp\Tools\UpdateApplication;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\ResumeTailoring;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationsAndMcpTest extends TestCase
{
    use RefreshDatabase;

    private function job(): JobPosting
    {
        return JobPosting::create([
            'source' => 'gupy', 'url' => 'https://x.test/1', 'fingerprint' => 'abc', 'title' => 'Desenvolvedor PHP Pleno',
            'company' => 'Acme', 'seniority' => 'pleno', 'area' => 'dev', 'techs' => ['PHP', 'Laravel'], 'description' => 'Laravel e PHP.',
        ]);
    }

    public function test_applying_from_job_does_not_duplicate(): void
    {
        $user = User::factory()->create();
        $job = $this->job();

        $this->actingAs($user)->post(route('jobs.apply', $job))->assertRedirect();
        $this->actingAs($user)->post(route('jobs.apply', $job))->assertRedirect();

        $this->assertSame(1, $user->jobApplications()->count());
        $this->actingAs($user)->get(route('applications.index'))->assertOk()->assertSee('Desenvolvedor PHP Pleno');
    }

    public function test_user_cannot_update_someone_elses_application(): void
    {
        $owner = User::factory()->create();
        $app = $owner->jobApplications()->create(['title' => 'Dev', 'applied_at' => today()]);

        $this->actingAs(User::factory()->create())
            ->patch(route('applications.update', $app), ['status' => 'recusado'])
            ->assertNotFound();
        $this->assertSame('enviado', $app->fresh()->status);
    }

    public function test_follow_up_alerts_after_15_and_30_days(): void
    {
        $app = new JobApplication(['status' => 'enviado', 'applied_at' => today()->subDays(20)]);
        $this->assertSame('follow_up', $app->alert());

        $app->applied_at = today()->subDays(31);
        $this->assertSame('no_response', $app->alert());

        $app->status = 'recusado';
        $this->assertNull($app->alert());
    }

    public function test_user_can_generate_mcp_token(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('ai.store'))->assertRedirect(route('ai.show'));
        $this->actingAs($user)->get(route('ai.show'))->assertOk()->assertSee('claude mcp add');
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_mcp_endpoint_requires_token(): void
    {
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
    }

    public function test_mcp_tools_search_tailor_and_track(): void
    {
        $user = User::factory()->create();
        $user->resume()->create(['full_name' => 'Fulano', 'skills' => 'PHP, MySQL']);
        $job = $this->job();

        GarimpoServer::actingAs($user)->tool(SearchJobs::class, ['tech' => 'PHP'])->assertOk()->assertSee('Desenvolvedor PHP Pleno');
        GarimpoServer::actingAs($user)->tool(PrepareTailoredResume::class, ['job_id' => $job->id])->assertOk()->assertSee(['NUNCA invente', 'Laravel']);
        GarimpoServer::actingAs($user)->tool(SaveTailoredResume::class, ['job_id' => $job->id, 'data' => ['nome' => 'Fulano', 'habilidades_tecnicas' => ['PHP', 'Laravel'], 'recomendacoes' => [['tipo' => 'remover', 'trecho' => 'Curso de violão', 'motivo' => 'Não tem relação com a vaga']]]])->assertOk();
        $tailoring = ResumeTailoring::sole();
        $this->assertSame('mcp', $tailoring->generated_by);
        $this->assertStringContainsString('<h1 style="text-align: center">Fulano</h1>', $tailoring->content);
        $this->assertSame('remover', $tailoring->recommendations[0]['tipo']);

        GarimpoServer::actingAs($user)->tool(AddApplication::class, ['job_id' => $job->id])->assertOk();
        $app = $user->jobApplications()->sole();
        GarimpoServer::actingAs($user)->tool(UpdateApplication::class, ['application_id' => $app->id, 'status' => 'entrevista', 'next_step' => 'Entrevista 05/12'])->assertOk();
        $this->assertSame('entrevista', $app->fresh()->status);
    }
}
