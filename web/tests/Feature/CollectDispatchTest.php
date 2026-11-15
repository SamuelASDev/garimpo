<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CollectDispatchTest extends TestCase
{
    public function test_dispatches_the_collect_workflow_on_github(): void
    {
        config(['services.github.token' => 'github_pat_teste', 'services.github.repo' => 'dono/garimpo']);
        Http::fake(['api.github.com/*' => Http::response(null, 204)]);

        $this->artisan('garimpo:coletar')->assertSuccessful();

        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.github.com/repos/dono/garimpo/actions/workflows/collect.yml/dispatches'
            && $r['ref'] === 'main' && $r->hasHeader('Authorization', 'Bearer github_pat_teste'));
    }

    public function test_reports_a_rejected_token_and_does_nothing_without_one(): void
    {
        config(['services.github.token' => 'github_pat_errado', 'services.github.repo' => 'dono/garimpo']);
        Http::fake(['api.github.com/*' => Http::response(['message' => 'Bad credentials'], 401)]);
        $this->artisan('garimpo:coletar')->assertFailed();

        config(['services.github.token' => null]);
        $this->artisan('garimpo:coletar')->assertFailed();
    }
}
