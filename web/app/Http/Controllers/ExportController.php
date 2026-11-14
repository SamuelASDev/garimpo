<?php

namespace App\Http\Controllers;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exporta o HTML do editor como arquivo: .pdf (Dompdf, texto real para o ATS) ou .docx (PhpWord).
 */
class ExportController extends Controller
{
    public function pdf(Request $request): Response
    {
        $data = $this->validated($request);

        $options = new Options;
        $options->setIsRemoteEnabled(false); // nunca busca arquivos externos a partir do HTML do usuário
        $options->setDefaultFont('Helvetica');

        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('exports.pdf', ['html' => $data['html']])->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($data).'.pdf"',
        ]);
    }

    public function word(Request $request): StreamedResponse
    {
        $data = $this->validated($request);

        $word = new PhpWord;
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(10);
        $section = $word->addSection(['marginTop' => 700, 'marginBottom' => 700, 'marginLeft' => 900, 'marginRight' => 900]);

        // Mesmo visual do editor: nome grande, títulos de seção azuis, pendências em amarelo.
        $html = $this->withStyle($this->toXhtml($data['html']), 'h1', 'font-size:20pt;font-weight:bold');
        $html = $this->withStyle($html, 'h2', 'font-size:11pt;font-weight:bold;color:#1f5f8b;margin-top:10pt', 'p');
        $html = $this->withStyle($html, 'mark', 'background-color:yellow', 'span');
        Html::addHtml($section, $html, false, false);

        return response()->streamDownload(
            fn () => IOFactory::createWriter($word, 'Word2007')->save('php://output'),
            $this->filename($data).'.docx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        );
    }

    /** O PhpWord só aceita XHTML: fecha tags como <br>, corrige aninhamento e converte entidades (&nbsp;). */
    private function toXhtml(string $html): string
    {
        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xhtml = '';
        foreach ($dom->getElementsByTagName('body')->item(0)?->childNodes ?? [] as $node) {
            $xhtml .= $dom->saveXML($node);
        }

        return $xhtml;
    }

    /**
     * Acrescenta CSS à tag, juntando com o style que ela já tiver (o Word recusa dois atributos style).
     * Opcionalmente troca a tag (ex.: h2 → p, mark → span).
     */
    private function withStyle(string $html, string $tag, string $css, ?string $newTag = null): string
    {
        $newTag ??= $tag;

        $html = preg_replace_callback("~<{$tag}(\s[^>]*)?>~i", function (array $m) use ($css, $newTag): string {
            $attributes = $m[1] ?? '';
            $existing = preg_match('~\sstyle="([^"]*)"~i', $attributes, $s) ? rtrim($s[1], '; ').';' : '';
            $attributes = preg_replace('~\sstyle="[^"]*"~i', '', $attributes);

            return "<{$newTag}{$attributes} style=\"{$existing}{$css}\">";
        }, $html);

        return preg_replace("~</{$tag}>~i", "</{$newTag}>", $html);
    }

    /** @return array{html: string, filename?: ?string} */
    private function validated(Request $request): array
    {
        return $request->validate([
            'html' => ['required', 'string', 'max:200000'],
            'filename' => ['nullable', 'string', 'max:80'],
        ]);
    }

    private function filename(array $data): string
    {
        return Str::slug($data['filename'] ?? 'curriculo') ?: 'curriculo';
    }
}
