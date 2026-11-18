<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicApiTest extends TestCase
{
    use RefreshDatabase;

    private function job(array $attributes = []): JobPosting
    {
        static $n = 0;
        $n++;

        return JobPosting::create($attributes + [
            'source' => 'gupy', 'url' => 'https://x.test/'.$n, 'fingerprint' => 'f'.$n, 'title' => 'Dev PHP', 'company' => 'Acme',
            'description' => str_repeat('Descrição completa da vaga. ', 50), 'techs' => ['PHP'], 'work_mode' => 'remoto', 'posted_at' => now(),
        ]);
    }

    /** @return array{0: User, 1: string} */
    private function key(): array
    {
        $user = User::factory()->create();
        [, $plain] = ApiKey::generateFor($user, 'teste');

        return [$user, $plain];
    }

    public function test_requests_without_a_valid_key_are_refused(): void
    {
        $this->getJson('/api/v1/vagas')->assertUnauthorized();
        $this->getJson('/api/v1/vagas', ['Authorization' => 'Bearer gp_errada'])->assertUnauthorized();
    }

    public function test_lists_filtered_jobs_with_link_to_original_and_only_a_short_summary(): void
    {
        [, $plain] = $this->key();
        $this->job(['title' => 'Dev PHP Remoto']);
        $this->job(['title' => 'Analista Java', 'techs' => ['Java'], 'work_mode' => 'presencial']);

        $response = $this->getJson('/api/v1/vagas?tecnologia=PHP&modo=remoto', ['Authorization' => 'Bearer '.$plain])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.titulo', 'Dev PHP Remoto')
            ->assertJsonPath('data.0.stack', 'PHP')
            ->assertJsonPath('meta.total', 1);

        $this->assertStringStartsWith('https://x.test/', $response->json('data.0.url_original'));
        $this->assertLessThanOrEqual(283, mb_strlen($response->json('data.0.resumo')));
        $this->assertArrayNotHasKey('description', $response->json('data.0'));
    }

    public function test_since_parameter_returns_only_jobs_collected_after_it(): void
    {
        [, $plain] = $this->key();
        $this->job(['title' => 'Antiga'])->forceFill(['created_at' => now()->subDays(2)])->save();
        $this->job(['title' => 'Nova']);

        $this->getJson('/api/v1/vagas?desde='.urlencode(now()->subDay()->toIso8601String()), ['X-Api-Key' => $plain])
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.titulo', 'Nova');
    }

    public function test_page_size_is_capped_and_invalid_filters_are_rejected(): void
    {
        [, $plain] = $this->key();

        $this->getJson('/api/v1/vagas?por_pagina=500', ['Authorization' => 'Bearer '.$plain])->assertUnprocessable();
        $this->getJson('/api/v1/vagas?modo=lua', ['Authorization' => 'Bearer '.$plain])->assertUnprocessable();
    }

    public function test_rate_limit_is_per_key(): void
    {
        [, $plain] = $this->key();
        [, $other] = $this->key();

        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/v1/vagas', ['Authorization' => 'Bearer '.$plain])->assertOk();
        }

        $this->getJson('/api/v1/vagas', ['Authorization' => 'Bearer '.$plain])->assertTooManyRequests();
        $this->getJson('/api/v1/vagas', ['Authorization' => 'Bearer '.$other])->assertOk();
    }

    public function test_user_creates_and_revokes_keys_and_only_the_hash_is_stored(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('dev.keys.store'), ['name' => 'Meu bot'])->assertSessionHas('api_key_plain');
        $plain = session('api_key_plain');
        $key = $user->apiKeys()->sole();
        $this->assertNotSame($plain, $key->key_hash);
        $this->getJson('/api/v1/estatisticas', ['Authorization' => 'Bearer '.$plain])->assertOk()->assertJsonPath('data.periodo_dias', 30);

        $intruder = User::factory()->create();
        $this->actingAs($intruder)->delete(route('dev.keys.destroy', $key))->assertNotFound();

        $this->actingAs($user)->delete(route('dev.keys.destroy', $key))->assertRedirect();
        $this->getJson('/api/v1/vagas', ['Authorization' => 'Bearer '.$plain])->assertUnauthorized();
    }

    public function test_dev_page_shows_the_docs_to_everyone_and_old_addresses_redirect(): void
    {
        $this->get(route('dev'))->assertOk()->assertSee('/api/v1/vagas')->assertSee('Entre');
        $this->get('/api')->assertRedirect('/dev#api');
        $this->get('/conectar-ia')->assertRedirect('/dev');

        $this->actingAs(User::factory()->create())->get(route('dev'))->assertOk()->assertSee('Conectar minha IA')->assertSee('Criar chave');
    }
}
