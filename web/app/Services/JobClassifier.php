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
        'Salesforce' => '\bsalesforce\b', 'Inglês' => '\b(ingl(ê|e)s|english)\b', 'Windows' => '\bwindows\b(?! ?server)',
    ];

    /**
     * Competências técnicas que não são linguagens/ferramentas (o que vagas de suporte, infra e dev pedem).
     *
     * @var array<string, string> rótulo => regex
     */
    public const COMPETENCIES = [
        // Suporte e infraestrutura
        'Hardware' => '\bhardware\b|arquitetura de (computadores|hardware)|componentes de (pc|computador)',
        'Montagem e manutenção de computadores' => 'montagem|desmontagem|manuten(ç|c)(ã|a)o (preventiva|corretiva|de (computadores|micros|equipamentos|hardware|notebooks?))|troca de (pe(ç|c)as|componentes)',
        'Formatação e instalação de sistemas' => 'formata(ç|c)(ã|a)o|instala(ç|c)(ã|a)o (e configura(ç|c)(ã|a)o )?(de )?(softwares?|sistemas|programas|aplicativos)',
        'Sistemas operacionais' => 'sistemas? operacion',
        'Nobreak' => 'no-?breaks?',
        'Cabeamento estruturado' => 'cabeamento|crimpagem|patch ?panel',
        'Redes locais' => 'redes? (locais|lan)|\blan\b|switch(es)?\b|roteadores?|wi-?fi|conectividade',
        'Atendimento ao usuário' => 'atendimento (presencial |remoto )?(aos? |ao )?(usu(á|a)rios?|colaboradores|clientes internos)|suporte (t(é|e)cnico )?(aos? )?(usu(á|a)rios?|colaboradores)|suporte presencial|hands-?on',
        'Suporte técnico' => 'suporte t(é|e)cnico|help ?desk|service ?desk|\bn[12]\b|n(í|i)vel [12]',
        'Diagnóstico de falhas' => 'diagn(ó|o)stic|troubleshoot|identifica(ç|c)(ã|a)o de falhas',
        'Inventário de equipamentos' => 'invent(á|a)rio|gest(ã|a)o de ativos|controle de (ativos|equipamentos)|parque (tecnol(ó|o)gico|de m(á|a)quinas)',
        'Impressoras' => 'impressoras?|multifuncionais',
        'Pacote Office' => 'pacote office|microsoft 365|office 365|\boutlook\b|\bpowerpoint\b',
        'Backup' => '\bbackups?\b',
        'Virtualização' => 'virtualiza(ç|c)(ã|a)o|vmware|hyper-?v|proxmox',
        'Firewall e VPN' => 'firewall|\bvpn\b|pfsense|fortinet|fortigate',
        'Telefonia e VoIP' => 'telefonia|\bvoip\b|\bpabx\b',
        'Abertura de chamados (ITSM)' => 'chamados|\btickets?\b|\bglpi\b|\bitsm\b|servicenow|\botrs\b',
        'Acesso remoto' => 'acesso remoto|anydesk|teamviewer|\brdp\b',
        'Monitoramento' => 'monitoramento|zabbix|grafana|prometheus|nagios',
        // Desenvolvimento
        'Banco de dados' => 'bancos? de dados|modelagem (de dados|relacional)',
        'APIs e integrações' => '\bapis?\b|integra(ç|c)(õ|o)es entre sistemas|webhooks?',
        'Testes automatizados' => 'testes? (unit(á|a)rios|automatizados|de integra(ç|c)(ã|a)o)|\btdd\b',
        'Arquitetura de software' => 'arquitetura de software|clean (code|architecture)|\bsolid\b|design patterns|padr(õ|o)es de projeto',
        'Metodologias ágeis' => 'metodologias? (á|a)geis|\bagile\b|\bsprints?\b',
        'Versionamento de código' => 'versionamento|controle de vers(ã|a)o|\bgithub\b|\bgitlab\b|\bbitbucket\b',
        'Documentação técnica' => 'documenta(ç|c)(ã|a)o t(é|e)cnica|documentar (processos|sistemas|procedimentos)',
        // Formação e outros requisitos
        'Ensino técnico' => 'ensino (m(é|e)dio ou )?t(é|e)cnico|curso t(é|e)cnico|t(é|e)cnico em (inform(á|a)tica|redes|eletr(ô|o)nica)',
        'Ensino superior em TI' => '(superior|gradua(ç|c)(ã|a)o|bacharelado|tecn(ó|o)logo)[^.\n]{0,40}(computa(ç|c)(ã|a)o|sistemas|\bti\b|tecnologia|software|inform(á|a)tica)',
        'CNH' => '\bcnh\b|carteira (nacional )?de habilita(ç|c)(ã|a)o',
    ];

    /** @var array<string, string> Competências comportamentais (soft skills). */
    public const SOFT_SKILLS = [
        'Proatividade' => 'proativ',
        'Relacionamento interpessoal' => 'relacionamento interpessoal|habilidades? interpessoa|bom relacionamento',
        'Comunicação' => 'boa comunica(ç|c)(ã|a)o|comunica(ç|c)(ã|a)o (clara|eficaz|assertiva)|comunicativ',
        'Trabalho em equipe' => 'trabalho em equipe|trabalhar em equipe|esp(í|i)rito de equipe|colaborativ',
        'Organização' => 'organizad[oa]|senso de organiza(ç|c)(ã|a)o|\bperfil organizado',
        'Agilidade' => '\bagilidade\b',
        'Autonomia' => 'autonomia|aut(ô|o)nom[oa]',
        'Atenção aos detalhes' => 'aten(ç|c)(ã|a)o (aos|a) detalhes|detalhista',
        'Resolução de problemas' => 'resolu(ç|c)(ã|a)o de problemas|resolver problemas|solucionar problemas',
        'Vontade de aprender' => 'vontade de aprender|aprendizado cont(í|i)nuo|autodidata|curiosidade',
        'Foco no cliente' => 'foco no cliente|orienta(ç|c)(ã|a)o (ao|para o) cliente|empatia',
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

    /** @return array{seniority: ?string, area: ?string, techs: list<string>, skills: list<string>, soft_skills: list<string>} */
    public function classify(string $title, string $description = ''): array
    {
        $title = Str::lower($title);
        $text = Str::lower($title."\n".strip_tags($description));

        return [
            'seniority' => $this->seniority($title) ?? $this->seniority($text, strict: true),
            'area' => $this->area($title) ?? $this->area($text),
            'techs' => $this->techs($text),
            'skills' => $this->match(self::COMPETENCIES, $text),
            'soft_skills' => $this->match(self::SOFT_SKILLS, $text),
        ];
    }

    /** Competências técnicas encontradas no texto. @return list<string> */
    public function competencies(string $text): array
    {
        return $this->match(self::COMPETENCIES, Str::lower($text));
    }

    /** Competências comportamentais encontradas no texto. @return list<string> */
    public function softSkills(string $text): array
    {
        return $this->match(self::SOFT_SKILLS, Str::lower($text));
    }

    /** @param  array<string, string>  $dictionary  @return list<string> */
    private function match(array $dictionary, string $text): array
    {
        return array_keys(array_filter($dictionary, fn (string $regex): bool => (bool) preg_match('~'.$regex.'~iu', $text)));
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
