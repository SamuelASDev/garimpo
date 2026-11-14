<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exporta o HTML do editor como .docx (Word / Google Docs / LibreOffice).
 */
class ExportController extends Controller
{
    public function word(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'html' => ['required', 'string', 'max:200000'],
            'filename' => ['nullable', 'string', 'max:80'],
        ]);

        $word = new PhpWord;
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(10);
        $section = $word->addSection(['marginTop' => 700, 'marginBottom' => 700, 'marginLeft' => 900, 'marginRight' => 900]);

        // Mesmo visual do editor: títulos de seção azuis, nome grande.
        $html = strtr($data['html'], [
            '<h1' => '<h1 style="font-size:20pt;font-weight:bold"',
            '<h2>' => '<p style="font-size:11pt;font-weight:bold;color:#1f5f8b;margin-top:10pt">',
            '</h2>' => '</p>',
            '<mark>' => '<span style="background-color:yellow">',
            '</mark>' => '</span>',
        ]);
        Html::addHtml($section, $html, false, false);

        $name = Str::slug($data['filename'] ?? 'curriculo') ?: 'curriculo';

        return response()->streamDownload(
            fn () => IOFactory::createWriter($word, 'Word2007')->save('php://output'),
            "$name.docx",
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        );
    }
}
