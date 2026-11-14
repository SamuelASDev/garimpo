<?php

namespace Tests\Feature;

use App\Models\JobPosting;
use App\Models\ResumeTailoring;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResumeEditorTest extends TestCase
{
    use RefreshDatabase;

    /** Simula a IA (Groq) devolvendo o JSON do modelo. */
    private function fakeAi(array $data): void
    {
        config(['services.groq.key' => 'teste', 'services.gemini.key' => null, 'services.openrouter.key' => null]);
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode($data)]]]])]);
    }

    public function test_new_user_sees_template_and_can_save(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('resume.edit'))->assertOk()->assertSee('(SEU NOME)')->assertSee('Importar PDF');
        $this->actingAs($user)->putJson(route('resume.update'), ['html' => '<h1>Fulano</h1><p>PHP e Laravel</p>'])->assertOk();

        $this->assertStringContainsString('PHP e Laravel', $user->fresh()->resume->plainText());
    }

    public function test_import_uses_ai_and_falls_back_to_plain_text(): void
    {
        $user = User::factory()->create();
        $text = str_repeat("Fulano de Tal\nDesenvolvedor PHP com Laravel e MySQL.\n", 3);

        $this->fakeAi(['nome' => 'Fulano de Tal', 'contato' => 'São Paulo - SP | [[ADICIONE: seu telefone]]']);
        $this->actingAs($user)->postJson(route('resume.import'), ['text' => $text])
            ->assertOk()->assertJsonPath('html', fn (string $html) => str_contains($html, 'Fulano de Tal') && str_contains($html, '<mark>ADICIONE: seu telefone</mark>'));

        config(['services.groq.key' => null]);
        $this->actingAs($user)->postJson(route('resume.import'), ['text' => $text])
            ->assertOk()->assertJsonPath('html', fn (string $html) => str_starts_with($html, '<p>Fulano de Tal'));
    }

    public function test_tailor_opens_editor_with_missing_items_and_recommendations(): void
    {
        $user = User::factory()->create();
        $user->resume()->create(['html' => '<h1>Fulano</h1><p>PHP, MySQL</p><ul><li><p>Curso de violão</p></li></ul>']);
        $job = JobPosting::create(['source' => 'gupy', 'url' => 'https://x.test/1', 'fingerprint' => 'a', 'title' => 'Dev PHP', 'techs' => ['PHP', 'Laravel'], 'description' => 'PHP e Laravel']);

        $this->fakeAi([
            'nome' => 'Fulano',
            'habilidades_tecnicas' => ['PHP', 'MySQL', '[[ADICIONE: Laravel, se você já usou]]'],
            'cursos' => ['Curso de violão'],
            'recomendacoes' => [['tipo' => 'remover', 'trecho' => 'Curso de violão', 'motivo' => 'Não tem relação com a vaga'], ['tipo' => 'invalido', 'trecho' => 'x']],
        ]);

        $this->actingAs($user)->post(route('jobs.tailor', $job))->assertRedirect(route('jobs.resume', $job));
        $tailoring = ResumeTailoring::sole();
        $this->assertSame('groq', $tailoring->generated_by);
        $this->assertCount(2, $tailoring->recommendations);
        $this->assertSame('ajustar', $tailoring->recommendations[1]['tipo']);

        $this->actingAs($user)->get(route('jobs.resume', $job))->assertOk()
            ->assertSee('ADICIONE: Laravel, se você já usou')
            ->assertSee('Curso de violão');

        $this->actingAs($user)->putJson(route('jobs.resume.update', $job), ['html' => '<p>PHP</p><p><mark>ADICIONE: Laravel</mark></p>'])
            ->assertOk()->assertJsonPath('match.score', 50)->assertJsonPath('placeholders', ['ADICIONE: Laravel']);
        $this->actingAs($user)->putJson(route('jobs.resume.update', $job), ['html' => '<p>PHP, Laravel</p>'])
            ->assertOk()->assertJsonPath('match.score', 100)->assertJsonPath('placeholders', []);
    }

    public function test_tailor_without_ai_copies_base_resume(): void
    {
        config(['services.groq.key' => null, 'services.gemini.key' => null, 'services.openrouter.key' => null]);
        $user = User::factory()->create();
        $user->resume()->create(['html' => '<p>Meu currículo base</p>']);
        $job = JobPosting::create(['source' => 'gupy', 'url' => 'https://x.test/1', 'fingerprint' => 'a', 'title' => 'Dev', 'techs' => ['PHP']]);

        $this->actingAs($user)->post(route('jobs.tailor', $job))->assertRedirect(route('jobs.resume', $job));
        $this->assertSame('<p>Meu currículo base</p>', ResumeTailoring::sole()->content);
    }

    public function test_export_word_downloads_docx(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->post(route('export.word'), ['html' => '<h1 style="text-align: center">Fulano</h1><h2 style="text-align: left">Experiência</h2><p>Curso<br>Faculdade &nbsp;Exemplo</p><ul><li><p>Item <mark>ADICIONE: x</mark></p></li></ul>', 'filename' => 'Currículo Teste']);

        $response->assertOk()->assertDownload('curriculo-teste.docx');
        $this->assertStringStartsWith('PK', $response->streamedContent()); // .docx é um zip
    }

    public function test_export_pdf_downloads_real_pdf_file(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->post(route('export.pdf'), ['html' => '<h1 style="text-align: center">Fulano de Tal</h1><h2>Experiência</h2><p>Programação em PHP</p>', 'filename' => 'Currículo Teste']);

        $response->assertOk()->assertDownload('curriculo-teste.pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
