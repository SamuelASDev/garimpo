<?php

namespace Tests\Feature;

use App\Mcp\Servers\GarimpoServer;
use App\Mcp\Tools\GetResume;
use App\Mcp\Tools\ListResumes;
use App\Mcp\Tools\SaveVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResumeVariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_variant_starts_as_copy_of_main_resume_and_is_edited_without_touching_it(): void
    {
        $user = User::factory()->create();
        $user->resume()->create(['html' => '<h1>Fulano</h1><p>PHP e Laravel</p>']);

        $this->actingAs($user)->post(route('variants.store'), [
            'name' => 'Back-end PHP', 'category' => 'Back-end', 'description' => 'Destacar Laravel', 'base' => 'principal',
        ])->assertRedirect();

        $variant = $user->resumeVariants()->sole();
        $this->assertSame('<h1>Fulano</h1><p>PHP e Laravel</p>', $variant->html);

        $this->actingAs($user)->putJson(route('variants.update', $variant), ['html' => '<h1>Fulano</h1><p>Laravel, APIs REST</p>'])->assertOk();
        $this->actingAs($user)->get(route('variants.edit', $variant))->assertOk()->assertSee('Back-end PHP');

        $this->assertStringContainsString('APIs REST', $variant->fresh()->html);
        $this->assertSame('<h1>Fulano</h1><p>PHP e Laravel</p>', $user->resume->fresh()->html);
    }

    public function test_variant_can_start_from_blank_template_without_main_resume(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('variants.store'), ['name' => 'Suporte', 'base' => 'modelo'])->assertRedirect();

        $this->assertNotEmpty($user->resumeVariants()->sole()->html);
    }

    public function test_someone_else_cannot_open_change_or_delete_a_variant(): void
    {
        $owner = User::factory()->create();
        $variant = $owner->resumeVariants()->create(['name' => 'Dados', 'html' => '<p>Python</p>']);
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get(route('variants.edit', $variant))->assertNotFound();
        $this->actingAs($intruder)->putJson(route('variants.update', $variant), ['html' => '<p>x</p>'])->assertNotFound();
        $this->actingAs($intruder)->postJson(route('variants.suggestions', $variant))->assertNotFound();
        $this->actingAs($intruder)->delete(route('variants.destroy', $variant))->assertNotFound();

        $this->assertSame('<p>Python</p>', $variant->fresh()->html);
    }

    public function test_ai_suggestions_use_the_variant_focus_as_target(): void
    {
        $user = User::factory()->create(['ai_consent_at' => now()]);
        $variant = $user->resumeVariants()->create([
            'name' => 'Back-end PHP', 'category' => 'Back-end', 'description' => 'Destacar Laravel', 'html' => '<h2>Cargo</h2><p>Suporte técnico</p>',
        ]);
        config(['services.groq.key' => 'teste', 'services.gemini.key' => null, 'services.openrouter.key' => null]);
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['recomendacoes' => [
            ['tipo' => 'ajustar', 'trecho' => 'Suporte técnico', 'novo' => 'Desenvolvedor Back-end PHP', 'motivo' => 'Foco da variação'],
        ]])]]]])]);

        $this->actingAs($user)->postJson(route('variants.suggestions', $variant), ['pages' => 1])
            ->assertOk()->assertJsonPath('recommendations.0.novo', 'Desenvolvedor Back-end PHP');

        Http::assertSent(fn ($request) => str_contains($request->body(), 'Back-end') && str_contains($request->body(), 'Destacar Laravel'));
        $this->assertCount(1, $variant->fresh()->recommendations);
    }

    public function test_variant_limit_per_user(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 20) as $i) {
            $user->resumeVariants()->create(['name' => 'V'.$i, 'html' => '<p>x</p>']);
        }

        $this->actingAs($user)->post(route('variants.store'), ['name' => 'Mais uma', 'base' => 'modelo'])->assertSessionHas('error');
        $this->assertSame(20, $user->resumeVariants()->count());
    }

    public function test_connected_ai_lists_and_reads_variants(): void
    {
        $user = User::factory()->create();
        $variant = $user->resumeVariants()->create(['name' => 'Back-end PHP', 'category' => 'Back-end', 'html' => '<p>Laravel e APIs</p>']);
        $other = User::factory()->create()->resumeVariants()->create(['name' => 'Alheia', 'html' => '<p>secreto</p>']);

        GarimpoServer::actingAs($user)->tool(ListResumes::class)
            ->assertOk()->assertSee('Back-end PHP');
        GarimpoServer::actingAs($user)->tool(GetResume::class, ['variant_id' => $variant->id])
            ->assertOk()->assertSee('Laravel e APIs');
        GarimpoServer::actingAs($user)->tool(GetResume::class, ['variant_id' => $other->id])
            ->assertHasErrors();
    }

    public function test_connected_ai_creates_and_updates_a_variant_without_touching_the_main_resume(): void
    {
        $user = User::factory()->create();
        $user->resume()->create(['html' => '<p>principal intacto</p>']);
        $data = ['nome' => 'Fulano', 'objetivo' => ['cargo' => 'Desenvolvedor Júnior', 'texto' => 'Vagas com JavaScript e Python.'], 'habilidades_tecnicas' => ['JavaScript', 'Python']];

        GarimpoServer::actingAs($user)->tool(SaveVariant::class, ['name' => 'Dev Júnior JS', 'category' => 'Full stack', 'description' => 'Vaga do deVagas', 'data' => $data])
            ->assertOk()->assertSee('criada');
        $variant = $user->resumeVariants()->sole();
        $this->assertSame('Dev Júnior JS', $variant->name);
        $this->assertStringContainsString('JavaScript', $variant->html);

        GarimpoServer::actingAs($user)->tool(SaveVariant::class, ['variant_id' => $variant->id, 'name' => 'Dev Júnior JS e Python', 'data' => $data])
            ->assertOk()->assertSee('atualizada');
        $this->assertSame('Dev Júnior JS e Python', $variant->fresh()->name);
        $this->assertSame(1, $user->resumeVariants()->count());
        $this->assertSame('<p>principal intacto</p>', $user->resume->fresh()->html);

        $other = User::factory()->create()->resumeVariants()->create(['name' => 'Alheia', 'html' => '<p>x</p>']);
        GarimpoServer::actingAs($user)->tool(SaveVariant::class, ['variant_id' => $other->id, 'name' => 'Invadida', 'data' => $data])
            ->assertHasErrors();
        $this->assertSame('Alheia', $other->fresh()->name);
    }
}
