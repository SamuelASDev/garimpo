<?php

namespace App\Services;

use App\Models\JobPosting;
use App\Models\Resume;

/**
 * Compara o currículo com a vaga usando o que um ATS procura: tecnologias, competências técnicas
 * (hardware, redes, atendimento...) e, à parte, competências comportamentais.
 */
class AtsMatcher
{
    public function __construct(private JobClassifier $classifier) {}

    /**
     * @return array{score: int, matched: list<string>, missing: list<string>, soft_matched: list<string>, soft_missing: list<string>, tips: list<string>}
     */
    public function match(Resume $resume, JobPosting $job): array
    {
        $text = $resume->plainText();
        $required = self::requirements($job);
        $have = $this->keywords($text);

        $matched = array_values(array_intersect($required, $have));
        $missing = array_values(array_diff($required, $have));
        $score = $required === [] ? 0 : (int) round(count($matched) / count($required) * 100);

        $softRequired = $job->soft_skills ?? [];
        $softHave = $this->classifier->softSkills($text);
        $softMatched = array_values(array_intersect($softRequired, $softHave));
        $softMissing = array_values(array_diff($softRequired, $softHave));

        $tips = [];
        if ($missing !== []) {
            $tips[] = 'Se você tem experiência com '.implode(', ', array_slice($missing, 0, 6)).', escreva isso no currículo com as mesmas palavras da vaga: o ATS procura o termo literal.';
        }
        if ($softMissing !== []) {
            $tips[] = 'A vaga valoriza '.implode(', ', $softMissing).'. Mostre isso com um exemplo real numa experiência (ex.: "Atendi 20 usuários por dia com agilidade") ou nas habilidades interpessoais.';
        }
        $tips[] = 'Deixe o cargo alvo do currículo parecido com o da vaga: "'.$job->title.'".';
        $tips[] = 'Use uma coluna só, sem tabelas, ícones ou fotos, e títulos simples como "Experiência" e "Formação".';

        return [
            'score' => $score,
            'matched' => $matched,
            'missing' => $missing,
            'soft_matched' => $softMatched,
            'soft_missing' => $softMissing,
            'tips' => $tips,
        ];
    }

    /** Tecnologias + competências técnicas pedidas pela vaga (o que entra no score). @return list<string> */
    public static function requirements(JobPosting $job): array
    {
        return array_values(array_unique([...($job->techs ?? []), ...($job->skills ?? [])]));
    }

    /** Tecnologias + competências técnicas presentes num texto. @return list<string> */
    public function keywords(string $text): array
    {
        return [...$this->classifier->techs($text), ...$this->classifier->competencies($text)];
    }
}
