<?php

namespace Tests\Feature;

use App\Mail\JobAlertDigest;
use App\Models\JobAlert;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class JobAlertsTest extends TestCase
{
    use RefreshDatabase;

    private int $n = 0;

    private function job(array $attrs): JobPosting
    {
        $this->n++;

        return JobPosting::create(['source' => 'gupy', 'url' => "https://x.test/{$this->n}", 'fingerprint' => "f{$this->n}", 'title' => "Vaga {$this->n}", 'posted_at' => now(), ...$attrs]);
    }

    public function test_user_creates_alert_from_list_filters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('alerts.store'), ['seniority' => 'junior', 'area' => 'dev', 'tech' => ['PHP', 'Laravel', 'Inexistente'], 'remote' => '1', 'hack' => 'x'])
            ->assertRedirect(route('alerts.index'));

        $alert = $user->jobAlerts()->sole();
        $this->assertSame(['seniority' => 'junior', 'area' => 'dev', 'tech' => ['PHP', 'Laravel'], 'remote' => true], $alert->filters);
        $this->assertSame('Júnior · Desenvolvimento · PHP + Laravel · Remoto', $alert->name);
        $this->actingAs($user)->get(route('alerts.index'))->assertOk()->assertSee('Júnior · Desenvolvimento');
    }

    public function test_alert_limit_and_ownership(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < JobAlert::MAX_PER_USER; $i++) {
            $user->jobAlerts()->create(['name' => "a$i", 'filters' => []]);
        }
        $this->actingAs($user)->post(route('alerts.store'), [])->assertSessionHas('error');

        $alert = $user->jobAlerts()->first();
        $this->actingAs(User::factory()->create())->delete(route('alerts.destroy', $alert))->assertNotFound();
        $this->actingAs($user)->patch(route('alerts.update', $alert))->assertRedirect();
        $this->assertFalse($alert->fresh()->active);
    }

    public function test_daily_digest_sends_one_email_with_new_matching_jobs_only_once(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $php = $user->jobAlerts()->create(['name' => 'PHP júnior', 'filters' => ['seniority' => 'junior', 'tech' => ['PHP']]]);
        $user->jobAlerts()->create(['name' => 'Remoto', 'filters' => ['remote' => true]]);
        $this->travel(1)->minutes();

        $match = $this->job(['seniority' => 'junior', 'techs' => ['PHP'], 'remote' => true]); // bate nos dois alertas
        $this->job(['seniority' => 'senior', 'techs' => ['Java']]);                          // não bate em nenhum

        $this->artisan('alerts:send')->assertSuccessful();

        Mail::assertSent(JobAlertDigest::class, 1);
        Mail::assertSent(JobAlertDigest::class, function (JobAlertDigest $mail) use ($user, $match, $php) {
            return $mail->hasTo($user->email)
                && count($mail->sections) === 1 // a mesma vaga não se repete no segundo alerta
                && $mail->sections[0]['alert']->is($php)
                && $mail->sections[0]['jobs']->pluck('id')->all() === [$match->id];
        });

        $this->artisan('alerts:send')->assertSuccessful(); // sem vaga nova desde o último envio
        Mail::assertSent(JobAlertDigest::class, 1);
    }

    public function test_no_email_for_unverified_or_paused(): void
    {
        Mail::fake();
        User::factory()->unverified()->create()->jobAlerts()->create(['name' => 'x', 'filters' => []]);
        User::factory()->create()->jobAlerts()->create(['name' => 'y', 'filters' => [], 'active' => false]);
        $this->travel(1)->minutes();
        $this->job([]);

        $this->artisan('alerts:send')->assertSuccessful();
        Mail::assertNothingSent();
    }

    public function test_email_renders_with_working_unsubscribe_link(): void
    {
        $user = User::factory()->create(['name' => 'Fulana']);
        $alert = $user->jobAlerts()->create(['name' => 'Dev PHP', 'filters' => ['tech' => ['PHP']]]);
        $job = $this->job(['title' => 'Desenvolvedor PHP Júnior', 'company' => 'Acme', 'techs' => ['PHP']]);
        $mail = new JobAlertDigest($user, [['alert' => $alert, 'jobs' => collect([$job]), 'total' => 1]]);

        $mail->assertHasSubject('🔔 1 vaga nova para você no '.config('app.name'));
        $mail->assertSeeInHtml('Desenvolvedor PHP Júnior');
        $mail->assertSeeInHtml('Parar de receber');

        $this->get($mail->unsubscribeUrl())->assertOk()->assertSee('alertas pausados');
        $this->assertFalse($alert->fresh()->active);
        $this->get(URL::route('alerts.unsubscribe', ['user' => $user->id]))->assertForbidden(); // sem assinatura não vale
    }
}
