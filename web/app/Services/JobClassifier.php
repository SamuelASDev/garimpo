<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Classifica vagas a partir do texto, sem IA: senioridade, área e tecnologias.
 */
class JobClassifier
{
    /** @var array<string, string> rótulo => regex */
    public const TECHS = [
        'PHP' => '\bphp\b', 'Laravel' => '\blaravel\b', 'Symfony' => '\bsymfony\b', 'WordPress' => '\bwordpress\b',
        'JavaScript' => '\b(javascript|js)\b', 'TypeScript' => '\btypescript\b', 'Node.js' => '\bnode(\.?js)?\b',
        'React' => '\breact(\.?js)?\b', 'React Native' => '\breact native\b', 'Vue' => '\bvue(\.?js)?\b',
        'Angular' => '\bangular\b', 'Next.js' => '\bnext\.?js\b', 'NestJS' => '\bnest\.?js\b', 'Svelte' => '\bsvelte\b',
        'HTML' => '\bhtml5?\b', 'CSS' => '\bcss3?\b', 'Tailwind' => '\btailwind\b', 'jQuery' => '\bjquery\b',
        'Python' => '\bpython\b', 'Django' => '\bdjango\b', 'Flask' => '\bflask\b', 'FastAPI' => '\bfastapi\b',
        'Java' => '\bjava\b(?!script)', 'Spring' => '\bspring( boot)?\b', 'Kotlin' => '\bkotlin\b',
        'C#' => '(?<![\w])c#', '.NET' => '(\.net\b|\bdotnet\b)', 'C++' => '\bc\+\+', 'Go' => '\b(golang|go lang)\b',
        'Rust' => '\brust\b', 'Ruby' => '\bruby\b', 'Rails' => '\brails\b', 'Elixir' => '\belixir\b', 'Swift' => '\bswift\b',
        'Flutter' => '\bflutter\b', 'Dart' => '\bdart\b', 'Android' => '\bandroid\b', 'iOS' => '\bios\b',
        'SQL' => '\bsql\b', 'MySQL' => '\bmysql\b', 'PostgreSQL' => '\bpostgres(ql)?\b', 'SQL Server' => '\bsql server\b',
        'Oracle' => '\boracle\b', 'MongoDB' => '\bmongo(db)?\b', 'Redis' => '\bredis\b', 'Elasticsearch' => '\belastic(search)?\b',
        'AWS' => '\b(aws|amazon web services)\b', 'Azure' => '\bazure\b', 'GCP' => '\b(gcp|google cloud)\b',
        'Docker' => '\bdocker\b', 'Kubernetes' => '\b(kubernetes|k8s)\b', 'Terraform' => '\bterraform\b',
        'Linux' => '\blinux\b', 'Windows Server' => '\bwindows server\b', 'Git' => '\bgit\b', 'CI/CD' => '\bci\s*/\s*cd\b',
        'REST' => '\brest(ful)?\b', 'GraphQL' => '\bgraphql\b', 'Microsserviços' => '\bmicro\s?servi(ç|c)(o|e)s?\b',
        'Power BI' => '\bpower ?bi\b', 'Excel' => '\bexcel\b', 'Spark' => '\bspark\b', 'Pandas' => '\bpandas\b',
        'Machine Learning' => '\b(machine learning|aprendizado de m(á|a)quina)\b', 'LLM' => '\b(llm|ia generativa|genai)\b',
        'Selenium' => '\bselenium\b', 'Cypress' => '\bcypress\b', 'Jest' => '\bjest\b', 'PHPUnit' => '\bphpunit\b',
        'Scrum' => '\bscrum\b', 'Kanban' => '\bkanban\b', 'ITIL' => '\bitil\b', 'Active Directory' => '\bactive directory\b',
        'Redes' => '(redes de computadores|tcp/ip|\bcisco\b|\bccna\b)', 'Figma' => '\bfigma\b', 'SAP' => '\bsap\b',
        'Salesforce' => '\bsalesforce\b', 'Inglês' => '\b(ingl(ê|e)s|english)\b',
    ];

    /** @var array<string, string> área => regex (a primeira que bater vence) */
    public const AREAS = [
        'suporte' => 'suporte|help ?desk|service ?desk|t(é|e)cnico de (ti|inform(á|a)tica)|\bsupport\b|n(í|i)vel [123]|\bn[123]\b',
        'seguranca' => 'seguran(ç|c)a da informa(ç|c)(ã|a)o|cyber|security|pentest|\bsoc\b',
        'infra' => 'infraestrutura|\binfra\b|devops|\bsre\b|cloud|sysadmin|administrador de (redes|sistemas)|\bredes\b|network',
        'dados' => '\bdados\b|data (engineer|scien|analyst)|cientista de dados|\bbi\b|machine learning|\bml\b',
        'qa' => '\bqa\b|qualidade de software|\btestes?\b|tester|quality assurance',
        'mobile' => 'mobile|android|\bios\b|flutter|react native',
        'design' => '\bux\b|\bui\b|product design',
        'gestao' => 'product owner|product manager|\bpo\b|scrum master|gerente de (projetos|ti)|coordenador de ti',
        'dev' => 'desenvolv|developer|programador|engenheir[oa] de software|software engineer|full ?stack|back ?-?end|front ?-?end|\bdev\b|tech lead',
    ];

    public const SENIORITIES = ['estagio', 'junior', 'pleno', 'senior', 'especialista'];

    /** @return array{seniority: ?string, area: ?string, techs: list<string>} */
    public function classify(string $title, string $description = ''): array
    {
        $title = Str::lower($title);
        $text = Str::lower($title."\n".strip_tags($description));

        return [
            'seniority' => $this->seniority($title) ?? $this->seniority($text, strict: true),
            'area' => $this->area($title) ?? $this->area($text),
            'techs' => $this->techs($text),
        ];
    }

    /** A vaga é de TI se o título indica uma área de TI ou cita uma tecnologia técnica. */
    public function isTech(string $title): bool
    {
        $title = Str::lower($title);

        return $this->area($title) !== null
            || array_diff($this->techs($title), ['Inglês', 'Excel', 'Scrum', 'Kanban', 'SAP']) !== [];
    }

    /** @return list<string> */
    public function techs(string $text): array
    {
        $text = Str::lower($text);
        $found = [];
        foreach (self::TECHS as $label => $regex) {
            if (preg_match('~'.$regex.'~iu', $text)) {
                $found[] = $label;
            }
        }

        return $found;
    }

    /**
     * No título vale tudo; na descrição ($strict) só palavras explícitas de nível,
     * porque termos como "principal" ou "arquitetura" aparecem em qualquer vaga.
     */
    private function seniority(string $text, bool $strict = false): ?string
    {
        return match (true) {
            (bool) preg_match('~\b(est(á|a)gi(o|ária|ário|ario)|intern(ship)?|trainee)\b~iu', $text) => 'estagio',
            ! $strict && (bool) preg_match('~(\bespecialista\b|\bstaff\b|\bprincipal\b|tech lead|l(í|i)der t(é|e)cnico|\barquitet)~iu', $text) => 'especialista',
            (bool) preg_match('~(\bs(ê|e)nior\b|\bsr\b|\biii\b)~iu', $text) => 'senior',
            (bool) preg_match('~(\bpleno\b|\bpl\b|\bmid\b|\bii\b)~iu', $text) => 'pleno',
            (bool) preg_match('~(\bj(ú|u)nior\b|\bjr\b|\bentry[- ]level\b)~iu', $text) => 'junior',
            default => null,
        };
    }

    private function area(string $text): ?string
    {
        foreach (self::AREAS as $area => $regex) {
            if (preg_match('~'.$regex.'~iu', $text)) {
                return $area;
            }
        }

        return null;
    }
}
