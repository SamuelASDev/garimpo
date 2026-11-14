<?php

namespace App\Services;

use App\Models\JobPosting;
use App\Models\Resume;

/**
 * Compara o currículo com a vaga usando as palavras-chave que um ATS procura.
 */
class AtsMatcher
{
    public function __construct(private JobClassifier $classifier) {}

    /** @return array{score: int, matched: list<string>, missing: list<string>, tips: list<string>} */
    public function match(Resume $resume, JobPosting $job): array
    {
        $jobTechs = $job->techs ?? [];
        $resumeTechs = $this->classifier->techs($resume->plainText());

        $matched = array_values(array_intersect($jobTechs, $resumeTechs));
        $missing = array_values(array_diff($jobTechs, $resumeTechs));
        $score = $jobTechs === [] ? 0 : (int) round(count($matched) / count($jobTechs) * 100);

        $tips = [];
        if ($missing !== []) {
            $tips[] = 'Se você tem experiência com '.implode(', ', $missing).', escreva esses termos exatamente assim no currículo: o ATS procura a palavra literal.';
        }
        $tips[] = 'Deixe o título do currículo parecido com o cargo da vaga: "'.$job->title.'".';
        if (mb_strlen((string) $resume->summary) < 150) {
            $tips[] = 'Escreva um resumo profissional de 3 a 5 linhas citando as tecnologias principais da vaga.';
        }
        $tips[] = 'Use uma coluna só, sem tabelas, ícones ou fotos, e títulos simples como "Experiência" e "Formação".';

        return ['score' => $score, 'matched' => $matched, 'missing' => $missing, 'tips' => $tips];
    }
}
