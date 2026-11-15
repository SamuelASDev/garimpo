<?php

namespace Tests\Feature;

use App\Models\JobAlert;
use App\Models\JobPosting;
use App\Models\ResumeTailoring;
use App\Models\User;
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

    public function test_stats_page_shows_charts(): void
    {
        JobPosting::create(['source' => 'gupy', 'url' => 'https://x.test/1', 'fingerprint' => 'a', 'title' => 'Dev', 'techs' => ['PHP'], 'seniority' => 'junior', 'posted_at' => now()]);

        $this->get(route('stats'))->assertOk()->assertSee('Tecnologias mais pedidas')->assertSee('PHP')->assertSee('Júnior');
    }
}
