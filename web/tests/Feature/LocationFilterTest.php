<?php

namespace Tests\Feature;

use App\Models\JobPosting;
use App\Services\JobIngestor;
use App\Services\Locations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_site_format_becomes_the_same_city_and_state(): void
    {
        foreach (['São Paulo, São Paulo, Brazil', 'São Paulo - SP', 'Sao Paulo / SP', 'São Paulo, SP, Brazil', 'São Paulo (Híbrido)'] as $location) {
            $this->assertSame(['city_key' => 'sao paulo', 'state' => 'SP'], array_intersect_key(Locations::parse($location), ['city_key' => 1, 'state' => 1]), $location);
        }
        $this->assertSame(['city' => null, 'city_key' => null, 'state' => null], Locations::parse('Remoto, Brazil'));
    }

    public function test_suggestions_group_spellings_and_filters_by_city_or_state(): void
    {
        app(JobIngestor::class)->ingest([
            ['source' => 'gupy', 'url' => 'https://a.test/1', 'title' => 'Dev PHP', 'company' => 'A', 'location' => 'São Paulo, São Paulo'],
            ['source' => 'vagas.com', 'url' => 'https://a.test/2', 'title' => 'Dev Java', 'company' => 'B', 'location' => 'Sao Paulo / SP'],
            ['source' => 'infojobs', 'url' => 'https://a.test/3', 'title' => 'Dev Go', 'company' => 'C', 'location' => 'Campinas - SP'],
            ['source' => 'infojobs', 'url' => 'https://a.test/4', 'title' => 'Dev Ruby', 'company' => 'D', 'location' => 'São José dos Pinhais - PR'],
        ]);

        $this->getJson(route('cities', ['q' => 'sao']))->assertOk()
            ->assertJsonPath('0.city', 'São Paulo')->assertJsonPath('0.state', 'SP')->assertJsonPath('0.total', 2)
            ->assertJsonPath('1.city', 'São José dos Pinhais')
            ->assertJsonPath('2.type', 'state')->assertJsonPath('2.state', 'SP')->assertJsonPath('2.total', 3);

        $this->get(route('jobs.index', ['city' => 'São Paulo']))->assertSee('Dev PHP')->assertSee('Dev Java')->assertDontSee('Dev Go');
        $this->get(route('jobs.index', ['state' => 'SP']))->assertSee('Dev Go')->assertDontSee('Dev Ruby');
        $this->assertSame(2, JobPosting::filter(['city' => 'sao paulo'])->count());
    }
}
