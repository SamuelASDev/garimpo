<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 14mm 16mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; line-height: 1.35; color: #111827; }
        h1 { font-size: 20pt; font-weight: bold; margin: 0 0 2pt; }
        h2 { font-size: 10.5pt; font-weight: bold; text-transform: uppercase; color: #1f5f8b; border-bottom: 1.5px solid #1f5f8b; padding-bottom: 1pt; margin: 11pt 0 5pt; }
        h3 { font-size: 10.5pt; font-weight: bold; margin: 6pt 0 2pt; }
        p { margin: 0 0 4pt; }
        ul, ol { margin: 0 0 5pt; padding-left: 14pt; }
        li p { margin: 0 0 1pt; }
        em { color: #4b5563; }
        mark { background: none; } /* pendências não saem destacadas no PDF final */
    </style>
</head>
<body>{!! $html !!}</body>
</html>
