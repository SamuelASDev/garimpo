<?php

namespace Tests\Feature;

use App\Models\JobPosting;
use App\Services\JobDescriptionFormatter as F;
use App\Services\JobIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobDescriptionFormatterTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $name): string
    {
        return file_get_contents(base_path("tests/Fixtures/descricoes/$name.txt"));
    }

    public function test_glued_text_gets_sections_and_items(): void
    {
        $text = F::normalize($this->fixture('grudada'));

        $this->assertStringContainsString("Laboratorial\n\nPrincipais atividades:\nElaborar", $text);
        $this->assertStringContainsString("laboratório físico-químico;\nPreparar", $text);
        $this->assertStringContainsString("(QD).\nApoiar", $text);
        $this->assertStringContainsString("\nSalário: R$ 4.102,31\nHorário:", $text);

        $html = F::toHtml($this->fixture('grudada'));
        $this->assertStringContainsString('<h4>Requisitos obrigatórios</h4>', $html);
        $this->assertStringContainsString('<li>Raciocínio analítico</li>', $html);
        $this->assertStringContainsString('<strong>Salário:</strong> R$ 4.102,31', $html);
    }

    public function test_inline_dash_lists_become_list_items(): void
    {
        $html = F::toHtml($this->fixture('hifens'));

        $this->assertStringContainsString('<h4>Atividades</h4>', $html);
        $this->assertSame(3, substr_count($html, '<li>Realizar'));
        // A linha depois da lista não pode ser engolida pelo último item.
        $this->assertStringContainsString("</ul>\n<p>Trabalho hibrido", $html);
    }

    public function test_markdown_is_rendered_and_camel_case_tech_names_are_kept(): void
    {
        $html = F::toHtml($this->fixture('markdown'));

        $this->assertStringContainsString('<h4>Requisitos</h4>', $html);
        $this->assertStringContainsString('<strong>Obrigatórios:</strong>', $html);
        $this->assertStringContainsString('<h4>Desejáveis</h4>', $html);
        $this->assertStringContainsString('PowerShell e SharePoint', $html);
        $this->assertStringNotContainsString('**', $html);
        $this->assertStringNotContainsString('##', $html);
    }

    public function test_entities_glued_links_and_json_are_cleaned_on_ingest(): void
    {
        $clean = JobIngestor::cleanDescription('Kubernetes.&nbsp;Conhecimentos<br>Trabalhe Conoscohttps://empresa.gupy.io/<script type="application/ld+json">{"@context":"x"}</script><ul><li>Item A</li><li>Item B</li></ul>');

        $this->assertStringNotContainsString('&nbsp;', $clean);
        $this->assertStringNotContainsString('@context', $clean);
        $this->assertStringContainsString('Conosco https://empresa.gupy.io/', $clean);
        $this->assertStringContainsString("- Item A\n", $clean);
    }

    public function test_description_html_cannot_inject_scripts_or_unsafe_links(): void
    {
        $html = F::toHtml("Vaga <script>alert(1)</script>\n\n[clique](javascript:alert(1)) <img src=x onerror=alert(1)>");

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_job_page_shows_formatted_description(): void
    {
        $job = JobPosting::create(['source' => 'vagas.com', 'url' => 'https://x.test/1', 'fingerprint' => 'a', 'title' => 'Técnico', 'description' => $this->fixture('grudada')]);

        $this->get(route('jobs.show', $job))->assertOk()->assertSee('<h4>Principais atividades</h4>', false);
    }
}
