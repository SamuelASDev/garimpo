<?php

namespace Tests\Feature;

use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobIngestTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function job(array $overrides = []): array
    {
        return array_merge([
            'source' => 'gupy',
            'url' => 'https://empresa.gupy.io/jobs/1',
            'title' => 'Desenvolvedor PHP Pleno',
            'company' => 'Empresa X',
            'location' => 'São Paulo - SP',
            'description' => 'Requisitos: Laravel, MySQL, Docker e Git.',
        ], $overrides);
    }

    public function test_ingest_requires_token(): void
    {
        config(['services.ingest.token' => 'segredo']);

        $this->postJson('/api/ingest', ['jobs' => [$this->job()]])->assertUnauthorized();
        $this->postJson('/api/ingest', ['jobs' => [$this->job()]], ['Authorization' => 'Bearer errado'])->assertUnauthorized();
    }

    public function test_ingest_classifies_dedupes_and_ignores_non_tech_jobs(): void
    {
        config(['services.ingest.token' => 'segredo']);

        $response = $this->postJson('/api/ingest', ['jobs' => [
            $this->job(),
            $this->job(['source' => 'linkedin', 'url' => 'https://linkedin.com/jobs/9', 'title' => 'Desenvolvedor PHP  Pleno', 'location' => 'Sao Paulo, SP']),
            $this->job(['url' => 'https://empresa.gupy.io/jobs/2', 'title' => 'Vendedor externo']),
            $this->job(['url' => 'https://url&quebrada.gupy.io/job/3', 'title' => 'Dev Java']),
        ]], ['Authorization' => 'Bearer segredo']);

        $response->assertOk()->assertExactJson(['created' => 1, 'duplicated' => 1, 'ignored' => 1, 'invalid' => 1]);

        $job = JobPosting::sole();
        $this->assertSame('pleno', $job->seniority);
        $this->assertSame('dev', $job->area);
        $this->assertEqualsCanonicalizing(['PHP', 'Laravel', 'MySQL', 'Docker', 'Git'], $job->techs);
    }

    public function test_job_page_shows_ats_match_for_user_with_resume(): void
    {
        $job = JobPosting::create([
            'source' => 'gupy', 'url' => 'https://x.test/1', 'fingerprint' => 'abc', 'title' => 'Dev PHP',
            'techs' => ['PHP', 'Laravel', 'Docker', 'AWS'],
        ]);
        $user = User::factory()->create();
        $user->resume()->create(['full_name' => 'Fulano', 'skills' => 'PHP, Laravel, MySQL']);

        $this->actingAs($user)->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee('50%')
            ->assertSee('Docker, AWS');
    }
}
