<?php

namespace Tests\Feature;

use App\Models\JobAlert;
use App\Models\JobPosting;
use App\Models\ResumeTailoring;
use App\Models\User;
use App\Services\JobClassifier;
use App\Services\JobIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAndFreshnessTest extends TestCase
{
    use RefreshDatabase;

    private function item(string $url, string $postedAt, array $extra = []): array
    {
        return ['source' => 'gupy', 'url' => $url, 'title' => 'Desenvolvedor PHP', 'company' => ['1' => 'Alfa', '2' => 'Beta', '3' => 'Gama', '4' => 'Delta'][substr($url, -1)], 'posted_at' => $postedAt, ...$extra];
    }

    public function test_first_collection_accepts_30_days_and_later_ones_only_7(): void
    {
        $ingestor = app(JobIngestor::class);

        $first = $ingestor->ingest([$this->item('https://a.test/1', now()->subDays(20)->toDateString()), $this->item('https://a.test/2', now()->subDays(40)->toDateString())]);
        $this->assertSame([1, 1], [$first['created'], $first['old']]);

        $this->travel(3)->hours();
        $later = $ingestor->ingest([$this->item('https://a.test/3', now()->subDays(10)->toDateString()), $this->item('https://a.test/4', now()->subDays(2)->toDateString())]);
        $this->assertSame([1, 1], [$later['created'], $later['old']]);
    }

    public function test_same_job_on_different_sites_is_not_duplicated(): void
    {
        $stats = app(JobIngestor::class)->ingest([
            ['source' => 'gupy', 'url' => 'https://g.test/1', 'title' => 'Analista de Suporte Sistemas Junior', 'company' => 'Nexdom Healthtech', 'location' => 'Remoto'],
            ['source' => 'linkedin', 'url' => 'https://l.test/1', 'title' => 'Analista de Suporte Sistemas Junior', 'company' => 'NEXDOM healthtech', 'location' => 'Brazil'],
            ['source' => 'indeed', 'url' => 'https://i.test/1', 'title' => 'Analista de Suporte Sistemas Júnior', 'company' => 'Nexdom Healthtech Ltda.', 'location' => 'Remoto'],
        ]);

        $this->assertSame([1, 2], [$stats['created'], $stats['duplicated']]);
    }

    public function test_pending_skips_jobs_already_collected_from_a_better_site(): void
    {
        config(['services.ingest.token' => 'segredo']);
        app(JobIngestor::class)->ingest([['source' => 'gupy', 'url' => 'https://g.test/1', 'title' => 'Desenvolvedor PHP', 'company' => 'Nexdom']]);

        $this->postJson('/api/ingest/pending', ['jobs' => [
            ['url' => 'https://vagas.test/1', 'title' => 'Desenvolvedor PHP', 'company' => 'NEXDOM'],
            ['url' => 'https://vagas.test/2', 'title' => 'Desenvolvedor Java', 'company' => 'Nexdom'],
        ]], ['Authorization' => 'Bearer segredo'])->assertOk()->assertExactJson(['urls' => ['https://vagas.test/2']]);
    }

    public function test_alerts_ignore_old_postings_that_arrived_now(): void
    {
        $alert = User::factory()->create()->jobAlerts()->create(['name' => 'Tudo', 'filters' => []]);
        $this->travel(1)->minutes();
        JobPosting::create(['source' => 'gupy', 'url' => 'https://x.test/old', 'fingerprint' => 'o', 'title' => 'Dev', 'posted_at' => now()->subDays(3)]);
        JobPosting::create(['source' => 'gupy', 'url' => 'https://x.test/new', 'fingerprint' => 'n', 'title' => 'Dev', 'posted_at' => now()->subHours(5)]);

        $this->assertSame(['https://x.test/new'], JobAlert::find($alert->id)->newJobs()->pluck('url')->all());
    }

    public function test_only_admins_reach_the_admin_panel(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.index'))->assertForbidden();

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->get(route('admin.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.users'))->assertOk()->assertSee($admin->email);
    }

    public function test_admin_edits_and_wipes_jobs_keeping_used_ones(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $used = JobPosting::create(['source' => 'gupy', 'url' => 'https://x.test/1', 'fingerprint' => 'a', 'title' => 'Dev', 'posted_at' => now()]);
        $other = JobPosting::create(['source' => 'gupy', 'url' => 'https://x.test/2', 'fingerprint' => 'b', 'title' => 'Dev', 'posted_at' => now()]);
        ResumeTailoring::create(['user_id' => $admin->id, 'job_posting_id' => $used->id, 'content' => '<p>x</p>', 'generated_by' => 'copia']);

        $this->actingAs($admin)->put(route('admin.jobs.update', $other), ['title' => 'Dev Laravel', 'url' => 'https://x.test/2', 'techs' => 'php, laravel, inventada', 'description' => 'Texto'])
            ->assertRedirect(route('admin.jobs'));
        $this->assertSame(['PHP', 'Laravel'], $other->fresh()->techs);

        $this->actingAs($admin)->delete(route('admin.jobs.destroyAll'), ['confirm' => 'nao'])->assertSessionHasErrors('confirm');
        $this->actingAs($admin)->delete(route('admin.jobs.destroyAll'), ['confirm' => 'APAGAR', 'keep_used' => 1])->assertRedirect();
        $this->assertSame([$used->id], JobPosting::pluck('id')->all());
    }

    public function test_work_mode_comes_from_site_or_text_and_filters_by_city(): void
    {
        app(JobIngestor::class)->ingest([
            ['source' => 'gupy', 'url' => 'https://g.test/1', 'title' => 'Dev PHP', 'company' => 'A', 'location' => 'Curitiba, PR', 'work_mode' => 'hibrido'],
            ['source' => 'vagas.com', 'url' => 'https://v.test/2', 'title' => 'Dev Java', 'company' => 'B', 'location' => 'São Paulo, SP', 'description' => 'Trabalho 100% home office.'],
            ['source' => 'infojobs', 'url' => 'https://i.test/3', 'title' => 'Dev Python', 'company' => 'C', 'location' => 'Curitiba, PR', 'description' => 'Atuação presencial no escritório.'],
            ['source' => 'infojobs', 'url' => 'https://i.test/4', 'title' => 'Dev Go', 'company' => 'D', 'location' => 'Recife, PE'],
        ]);

        $this->assertSame(['hibrido', 'remoto', 'presencial', null], JobPosting::orderBy('id')->pluck('work_mode')->all());
        $this->assertTrue(JobPosting::where('url', 'https://v.test/2')->value('remote'));

        $this->get(route('jobs.index', ['city' => 'curitiba']))->assertOk()->assertSee('Dev PHP')->assertSee('Dev Python')->assertDontSee('Dev Java');
        $this->get(route('jobs.index', ['mode' => 'presencial']))->assertOk()->assertSee('Dev Python')->assertDontSee('Dev PHP')
            ->assertSee('Senioridade não identificada');
    }

    public function test_errors_are_friendly_and_in_portuguese(): void
    {
        $this->get('/vagas/999999')->assertNotFound()->assertSee('Página não encontrada')->assertDontSee('Illuminate');
        $this->post(route('register'), ['email' => 'nao-e-email'])->assertSessionHasErrors(['email' => 'Informe um e-mail válido.']);

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->patch(route('admin.users.admin', $admin))->assertSessionHasErrors('user');
        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_english_is_a_competency_and_each_job_has_one_main_stack(): void
    {
        $found = app(JobClassifier::class)->classify('Desenvolvedor PHP', 'Laravel, MySQL e inglês avançado.');
        $this->assertNotContains('Inglês', $found['techs']);
        $this->assertContains('Inglês', $found['skills']);

        $this->assertSame('PHP', JobClassifier::stackOf('Desenvolvedor PHP Pleno', ['PHP', 'JavaScript', 'React']));
        $this->assertSame('Mobile', JobClassifier::stackOf('Dev Mobile', ['React Native', 'React']));
        $this->assertSame('Node.js', JobClassifier::stackOf('Dev Back-end', ['Node.js', 'NestJS', 'AWS']));
        $this->assertNull(JobClassifier::stackOf('Analista de Suporte', ['Windows', 'Linux']));
    }

    public function test_stats_page_shows_charts(): void
    {
        JobPosting::create(['source' => 'gupy', 'url' => 'https://x.test/1', 'fingerprint' => 'a', 'title' => 'Dev', 'techs' => ['PHP'], 'seniority' => 'junior', 'posted_at' => now()]);

        $this->get(route('stats'))->assertOk()->assertSee('Stacks com mais vagas')->assertSee('Tecnologias mais pedidas')->assertSee('PHP')->assertSee('Júnior');
    }
}
