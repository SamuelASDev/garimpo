<?php

namespace Tests\Feature;

use App\Models\JobPosting;
use App\Models\ResumeTailoring;
use App\Models\User;
use App\Services\ContactMasker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * LGPD: aceite, consentimento da IA, minimização, acesso aos dados e exclusão.
 */
class PrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_requires_terms_and_records_consents(): void
    {
        $data = ['name' => 'Fulana', 'email' => 'fulana@example.com', 'password' => 'password', 'password_confirmation' => 'password'];

        $this->post('/register', $data)->assertSessionHasErrors('terms');
        $this->assertGuest();

        $this->post('/register', $data + ['terms' => '1'])->assertRedirect();
        $user = User::sole();
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertNull($user->ai_consent_at); // IA grátis é opcional e começa desligada
    }

    public function test_free_ai_is_blocked_without_consent_and_nothing_is_sent(): void
    {
        Http::fake();
        config(['services.groq.key' => 'teste']);
        $user = User::factory()->create(['ai_consent_at' => null]);
        $user->resume()->create(['html' => '<p>PHP</p>']);

        $this->actingAs($user)->postJson(route('resume.assistant'), ['message' => 'melhore', 'html' => '<p>PHP</p>'])->assertForbidden();
        $this->actingAs($user)->postJson(route('resume.import'), ['text' => str_repeat('Desenvolvedor PHP com Laravel. ', 5)])
            ->assertOk()->assertJsonPath('message', fn (string $m) => str_contains($m, 'não autorizou'));
        Http::assertNothingSent();

        $this->actingAs($user)->patch(route('privacy.consent'), ['ai_consent' => 1])->assertRedirect();
        $this->assertTrue($user->fresh()->hasAiConsent());
    }

    public function test_contacts_are_masked_before_reaching_the_ai(): void
    {
        config(['services.groq.key' => 'teste', 'services.gemini.key' => null, 'services.openrouter.key' => null]);
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['resposta' => 'ok', 'html' => '<p>[EMAIL_1] | [TELEFONE_2]</p>'])]]]])]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('resume.assistant'), ['message' => 'arrume', 'html' => '<p>fulano@gmail.com | (67) 99954-7095</p>'])
            ->assertOk()->assertJsonPath('html', '<p>fulano@gmail.com | (67) 99954-7095</p>');

        Http::assertSent(fn ($request) => ! str_contains($request->body(), 'fulano@gmail.com') && ! str_contains($request->body(), '99954'));
    }

    public function test_masker_keeps_periods_and_strips_job_contacts(): void
    {
        $this->assertSame('Período 2024-2025, ligue [contato removido: veja o anúncio original]', ContactMasker::strip('Período 2024-2025, ligue (11) 3333-4444'));
    }

    public function test_export_contains_all_user_data(): void
    {
        $user = User::factory()->create(['name' => 'Fulana']);
        $user->resume()->create(['html' => '<p>Meu currículo</p>']);
        $user->jobApplications()->create(['title' => 'Dev PHP', 'applied_at' => today()]);

        $response = $this->actingAs($user)->get(route('privacy.export'));
        $response->assertOk()->assertDownload('meus-dados-garimpo.json');
        $json = json_decode($response->streamedContent(), true);

        $this->assertSame('Fulana', $json['conta']['nome']);
        $this->assertSame('Meu currículo', $json['curriculo_principal']['texto']);
        $this->assertSame('Dev PHP', $json['candidaturas'][0]['title']);
    }

    public function test_deleting_account_removes_resumes_applications_and_ai_tokens(): void
    {
        Artisan::call('passport:client', ['--personal' => true, '--name' => 'Garimpo', '--provider' => 'users', '--no-interaction' => true]);
        $user = User::factory()->create();
        $user->resume()->create(['html' => '<p>x</p>']);
        $job = JobPosting::create(['source' => 'gupy', 'url' => 'https://x.test/1', 'fingerprint' => 'a', 'title' => 'Dev']);
        ResumeTailoring::create(['user_id' => $user->id, 'job_posting_id' => $job->id, 'content' => '<p>x</p>', 'generated_by' => 'copia']);
        $user->createToken('mcp', ['mcp:use']);

        $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertRedirect('/');

        $this->assertDatabaseCount('resumes', 0);
        $this->assertDatabaseCount('resume_tailorings', 0);
        $this->assertDatabaseCount('oauth_access_tokens', 0);
    }

    public function test_legal_pages_are_public(): void
    {
        $this->get(route('legal.privacy'))->assertOk()->assertSee('Lei Geral de Proteção de Dados');
        $this->get(route('legal.terms'))->assertOk()->assertSee('Termos de Uso');
    }
}
